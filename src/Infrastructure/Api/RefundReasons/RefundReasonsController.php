<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Api\RefundReasons;

use Crehler\PaymentBundle\Application\Port\Driven\RefundReasonProviderInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\{Autoconfigure, AutowireIterator};
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\Routing\Attribute\Route;

use function array_map;

/**
 * Admin endpoint feeding the order-detail refund modal with the provider's predefined
 * refund reasons. Returns an empty list when the order's provider exposes no reasons
 * (the modal then shows only the free-text note field, unchanged).
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Autoconfigure(public: true)]
final class RefundReasonsController
{
    /**
     * @param iterable<RefundReasonProviderInterface> $reasonProviders
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        #[AutowireIterator(RefundReasonProviderInterface::class)]
        private readonly iterable $reasonProviders,
    ) {
    }

    #[Route(
        path: '/api/_action/crehler-payment/order/{orderId}/refund-reasons',
        name: 'api.action.crehler-payment.refund-reasons',
        requirements: ['orderId' => '[0-9a-fA-F]{32}'],
        methods: ['GET'],
    )]
    public function refundReasons(string $orderId, Context $context): JsonResponse
    {
        $order = $this->loadOrder($orderId, $context);

        if ($order === null) {
            return new JsonResponse(['error' => 'order_not_found'], Response::HTTP_NOT_FOUND);
        }

        $provider = $this->resolveProvider($order);

        if ($provider === null) {
            return new JsonResponse(['required' => false, 'reasons' => []]);
        }

        $reasons = array_map(
            static fn ($reason) => ['code' => $reason->code, 'label' => $reason->label],
            $provider->getRefundReasons(),
        );

        return new JsonResponse([
            'required' => $provider->isReasonRequired(),
            'reasons' => $reasons,
        ]);
    }

    private function resolveProvider(OrderEntity $order): ?RefundReasonProviderInterface
    {
        foreach ($order->getTransactions() ?? [] as $transaction) {
            $handlerIdentifier = $transaction->getPaymentMethod()?->getHandlerIdentifier();
            if ($handlerIdentifier === null) {
                continue;
            }

            foreach ($this->reasonProviders as $provider) {
                if ($provider->supports($handlerIdentifier)) {
                    return $provider;
                }
            }
        }

        return null;
    }

    private function loadOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.paymentMethod');

        return $this->orderRepository->search($criteria, $context)->first();
    }
}
