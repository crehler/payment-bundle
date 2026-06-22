<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Api\RefundSupport;

use Crehler\PaymentBundle\Application\Port\Driven\RefundProviderPort;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\{Autoconfigure, AutowireIterator};
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin endpoint telling the order-detail "Zwroty płatności" tab whether the order's
 * payment provider can actually process refunds.
 *
 * A provider declares refund support by implementing RefundProviderPort, matched by the
 * payment-method handler identifier (the handler FQCN Shopware stores on payment_method).
 * Captures are created for every Crehler provider, so the tab/refund card would otherwise
 * render for gateways with no refund handler (e.g. PayNow today) and a refund attempt would
 * fail deep in the native processor with "unknown refund handler". This lets the UI hide the
 * tab / show a "not supported" state instead.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Autoconfigure(public: true)]
final class RefundSupportController
{
    /**
     * @param iterable<RefundProviderPort> $refundProviders
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        #[AutowireIterator(RefundProviderPort::class)]
        private readonly iterable $refundProviders,
    ) {
    }

    #[Route(
        path: '/api/_action/crehler-payment/order/{orderId}/refund-supported',
        name: 'api.action.crehler-payment.refund-supported',
        requirements: ['orderId' => '[0-9a-fA-F]{32}'],
        methods: ['GET'],
    )]
    public function refundSupported(string $orderId, Context $context): JsonResponse
    {
        $order = $this->loadOrder($orderId, $context);

        if ($order === null) {
            return new JsonResponse(['error' => 'order_not_found'], Response::HTTP_NOT_FOUND);
        }

        // An order may carry several transactions (retries, payment-method changes); refunds
        // are supported if any of them is handled by a provider that implements a refund port.
        foreach ($order->getTransactions() ?? [] as $transaction) {
            $handlerIdentifier = $transaction->getPaymentMethod()?->getHandlerIdentifier();
            if ($handlerIdentifier === null) {
                continue;
            }

            foreach ($this->refundProviders as $provider) {
                if ($provider->supports($handlerIdentifier)) {
                    return new JsonResponse(['supported' => true]);
                }
            }
        }

        return new JsonResponse(['supported' => false]);
    }

    private function loadOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.paymentMethod');

        return $this->orderRepository->search($criteria, $context)->first();
    }
}
