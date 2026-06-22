<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Api;

use Crehler\PaymentBundle\Shared\{EnhancedLogger, FinalizeTokenService};
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\{JWTFactoryV2, TokenFactoryInterfaceV2 as TokenFactoryInterface, TokenStruct};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\{Autoconfigure, Autowire};
use Symfony\Component\HttpFoundation\{RedirectResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

use function is_string;

/**
 * Browser-facing payment-return endpoint with a short URL.
 *
 * Payment gateways (Tpay sandbox confirmed at ~512 chars) truncate long
 * return URLs in their Location-header redirect, which corrupts the
 * Shopware payment JWT (drops the signature segment, leaving 2 dots → 1).
 * The result is CHECKOUT__INVALID_PAYMENT_TOKEN on the customer's return.
 *
 * This controller exposes a short, HMAC-signed URL that the gateway can
 * redirect to without truncation, then regenerates a fresh Shopware
 * payment token and 302-redirects the browser to /payment/finalize-transaction.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Autoconfigure(public: true)]
final class PaymentReturnController extends AbstractController
{
    public function __construct(
        private readonly FinalizeTokenService $finalizeTokenService,
        private readonly EntityRepository $orderTransactionRepository,
        #[Autowire(service: JWTFactoryV2::class)]
        private readonly TokenFactoryInterface $tokenFactory,
        private readonly RouterInterface $router,
        private readonly EnhancedLogger $logger,
    ) {
    }

    #[Route(
        path: 'payment/return/{orderTransactionId}',
        name: 'crehler.bundle.payment.return',
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function handle(string $orderTransactionId, Request $request): Response
    {
        $sig = (string) $request->query->get('sig', '');
        $exp = $request->query->getInt('exp');

        if (!$this->finalizeTokenService->verifySignature($orderTransactionId, $sig, $exp)) {
            $this->logger->warning('Payment return: invalid or expired signature', [
                'orderTransactionId' => $orderTransactionId,
            ]);

            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        $context = Context::createDefaultContext();
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');

        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository
            ->search($criteria, $context)
            ->first();

        if ($orderTransaction === null) {
            $this->logger->warning('Payment return: transaction not found', [
                'orderTransactionId' => $orderTransactionId,
            ]);

            return new Response('Transaction not found', Response::HTTP_NOT_FOUND);
        }

        $orderId = $orderTransaction->getOrderId();

        $customFields = $orderTransaction->getCustomFields() ?? [];
        $storedFinishUrl = $customFields['crehler_payment_finish_url'] ?? null;
        $storedErrorUrl = $customFields['crehler_payment_error_url'] ?? null;

        $finishUrl = is_string($storedFinishUrl) && $storedFinishUrl !== ''
            ? $storedFinishUrl
            : $this->router->generate(
                'frontend.checkout.finish.page',
                ['orderId' => $orderId],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        $errorUrl = is_string($storedErrorUrl) && $storedErrorUrl !== ''
            ? $storedErrorUrl
            : $this->router->generate(
                'frontend.checkout.finish.page',
                ['orderId' => $orderId, 'changedPayment' => false, 'paymentFailed' => true],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        $jwt = $this->tokenFactory->generateToken(new TokenStruct(
            id: null,
            token: null,
            paymentMethodId: $orderTransaction->getPaymentMethodId(),
            transactionId: $orderTransactionId,
            finishUrl: $finishUrl,
            expires: 1800,
            errorUrl: $errorUrl,
        ));

        $redirectUrl = $this->router->generate(
            'payment.finalize.transaction',
            ['_sw_payment_token' => $jwt],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new RedirectResponse($redirectUrl);
    }
}
