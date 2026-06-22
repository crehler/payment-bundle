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
use Crehler\PaymentBundle\Domain\Exception\EmptyCartException;
use Crehler\PaymentBundle\Domain\Repository\OrderRepositoryInterface;
use Crehler\PaymentBundle\Domain\ValueObjects\BlikPayment;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\{AbstractCartOrderRoute, CartOrderRoute, CartService};
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
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

    private function getCart(SalesChannelContext $context): Cart
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

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
