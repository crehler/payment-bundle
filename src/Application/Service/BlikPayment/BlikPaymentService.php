<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service\BlikPayment;

use Crehler\PaymentBundle\Application\DTO\BlikPayment\{BlikPaymentRequestDTO, BlikPaymentResponseDTO};
use Crehler\PaymentBundle\Application\Port\Driven\OrderTransactionRepositoryInterface;
use Crehler\PaymentBundle\Domain\Enum\PaymentType;
use Crehler\PaymentBundle\Domain\Exception\{EmptyCartException, PaymentMethodNotFoundException};
use Crehler\PaymentBundle\Domain\Repository\OrderRepositoryInterface;
use Crehler\PaymentBundle\Domain\ValueObjects\BlikPayment;
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentMethodContractResolver;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\{AbstractCartOrderRoute, CartOrderRoute, CartService};
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\{PaymentMethodEntity, PaymentProcessor};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

use function preg_replace;

final readonly class BlikPaymentService
{
    public function __construct(
        private CartService $cartService,
        private OrderRepositoryInterface $orderRepository,
        private OrderTransactionRepositoryInterface $orderTransactionRepository,
        #[Autowire(service: CartOrderRoute::class)]
        private AbstractCartOrderRoute $cartOrderRoute,
        #[Autowire(service: 'sales_channel.payment_method.repository')]
        private SalesChannelRepository $salesChannelPaymentMethodRepository,
        private PaymentMethodContractResolver $contractResolver,
        private PaymentProcessor $paymentProcessor,
        private RouterInterface $router,
        private EnhancedLogger $logger,
    ) {
    }

    public function process(BlikPaymentRequestDTO $requestDTO): BlikPaymentResponseDTO
    {
        $blikCode = $this->formatBlikCode($requestDTO->blikCode);
        $blikPayment = new BlikPayment(
            paymentMethodId: $requestDTO->paymentMethodId,
            blikCode: $blikCode,
        );

        // The documented paymentMethodId is now honored instead of silently ignored:
        // switching the context BEFORE the cart is (re)calculated is what makes the
        // order transaction carry the requested method, because Shopware derives the
        // cart transaction from the context. Previously the id was validated into a
        // value object and then dropped, so a headless client following the docs got
        // whatever the session context happened to hold — for a card context the card
        // handler took over and the BLIK code was never used (WT-910).
        $this->applyRequestedPaymentMethod($blikPayment->paymentMethodId, $requestDTO->salesChannelContext);

        $cart = $this->getCart($requestDTO->salesChannelContext);
        $order = $this->createOrder($cart, $requestDTO->salesChannelContext);
        $orderId = $order->getId();

        if ($requestDTO->customFields !== null && $requestDTO->customFields !== []) {
            $this->orderRepository->updateCustomFields(
                $orderId,
                $requestDTO->customFields,
                $requestDTO->salesChannelContext->getContext(),
            );
        }

        $request = $this->attachBlikCodeToRequest(
            request: $requestDTO->request,
            blikCode: $blikPayment->blikCode,
        );

        try {
            $redirectUrl = $this->processPayment(
                orderId: $orderId,
                request: $request,
                context: $requestDTO->salesChannelContext,
                finishUrl: $requestDTO->finishUrl,
                errorUrl: $requestDTO->errorUrl,
            );

            return new BlikPaymentResponseDTO(
                success: true,
                redirectUrl: $redirectUrl,
                orderId: $orderId
            );
        } catch (Throwable $e) {
            // Log the gateway/SDK detail server-side only; the client gets a generic
            // message. Fail the just-created transaction so the orphaned order does
            // not linger in OPEN and pollute /cr/payment/check polling.
            $this->logger->error('BLIK payment failed', ['orderId' => $orderId, 'exception' => $e]);

            $this->failOrderTransaction($order, $requestDTO->salesChannelContext->getContext());

            return new BlikPaymentResponseDTO(
                success: false,
                errorMessage: 'BLIK payment failed',
                orderId: $orderId
            );
        }
    }

    private function failOrderTransaction(OrderEntity $order, Context $context): void
    {
        $transactionId = $order->getPrimaryOrderTransaction()?->getId();

        if ($transactionId === null) {
            return;
        }

        try {
            $this->orderTransactionRepository->markTransactionFailed($transactionId, $context);
        } catch (Throwable $cleanupError) {
            $this->logger->error('Failed to mark BLIK order transaction as failed', [
                'orderId' => $order->getId(),
                'exception' => $cleanupError,
            ]);
        }
    }

    /**
     * Point the context at the requested BLIK method so the order is created with it.
     *
     * Both checks matter and both must reject before an order exists: an unknown or
     * channel-unavailable id, and an id that resolves to something other than a
     * Crehler BLIK method — this route must not become a way to drive a card or bank
     * payment (or a foreign handler that merely ends with "BlikHandler").
     *
     * The sales-channel-scoped repository already restricts to methods that are active
     * and assigned to the current channel, so a valid-looking id from another channel
     * is rejected too.
     *
     * @throws PaymentMethodNotFoundException
     */
    private function applyRequestedPaymentMethod(string $paymentMethodId, SalesChannelContext $context): void
    {
        if ($paymentMethodId === '') {
            // Documented as required, but older integrations omit it. Keep the previous
            // behaviour (method from the context) rather than breaking them.
            $this->logger->info('BLIK request without paymentMethodId — falling back to the context method', [
                'contextPaymentMethodId' => $context->getPaymentMethod()->getId(),
            ]);

            return;
        }

        // A non-UUID would reach the DAL and blow up as a 500 inside Criteria; reject it
        // as the client error it is.
        if (!Uuid::isValid($paymentMethodId)) {
            throw new PaymentMethodNotFoundException('Payment method id is not a valid UUID');
        }

        $paymentMethod = $this->salesChannelPaymentMethodRepository
            ->search(new Criteria([$paymentMethodId]), $context)
            ->getEntities()
            ->first();

        if (!$paymentMethod instanceof PaymentMethodEntity) {
            throw new PaymentMethodNotFoundException('Payment method is not available in this sales channel');
        }

        if ($this->contractResolver->resolve($paymentMethod)?->type !== PaymentType::BLIK) {
            throw new PaymentMethodNotFoundException('Payment method is not a BLIK method');
        }

        if ($context->getPaymentMethod()->getId() === $paymentMethodId) {
            return; // already selected — nothing to switch
        }

        // Struct assignment keeps the switch scoped to this request: the customer's
        // stored context must not be rewritten by a one-shot payment call.
        $context->assign(['paymentMethod' => $paymentMethod]);
    }

    private function getCart(SalesChannelContext $context): Cart
    {
        // caching: false — the cart must be recalculated against the context we just
        // switched, otherwise a cart loaded earlier in the request would still carry a
        // transaction for the previous payment method.
        $cart = $this->cartService->getCart($context->getToken(), $context, caching: false);

        if ($cart->getLineItems()->count() === 0) {
            throw new EmptyCartException();
        }

        return $cart;
    }

    private function createOrder(Cart $cart, SalesChannelContext $context): OrderEntity
    {
        $dataBag = new RequestDataBag([
            'tos' => true,
        ]);

        $orderResponse = $this->cartOrderRoute->order($cart, $context, $dataBag);
        $orderId = $orderResponse->getOrder()->getId();

        return $this->orderRepository->getOrderWithTransactions($orderId);
    }

    private function processPayment(
        string $orderId,
        Request $request,
        SalesChannelContext $context,
        ?string $finishUrl,
        ?string $errorUrl,
    ): string {
        $finishUrl ??= $this->router->generate('frontend.checkout.finish.page', [
            'orderId' => $orderId,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $errorUrl ??= $this->router->generate('frontend.checkout.finish.page', [
            'orderId' => $orderId,
            'paymentFailed' => true,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = $this->paymentProcessor->pay(
            orderId: $orderId,
            request: $request,
            salesChannelContext: $context,
            finishUrl: $finishUrl,
            errorUrl: $errorUrl,
        );

        return $response?->getTargetUrl() ?? '';
    }

    private function formatBlikCode(string $blikCode): string
    {
        return preg_replace('/\s+/', '', $blikCode) ?? $blikCode;
    }

    private function attachBlikCodeToRequest(Request $request, string $blikCode): Request
    {
        $request->request->set('blikCode', $blikCode);

        return $request;
    }
}
