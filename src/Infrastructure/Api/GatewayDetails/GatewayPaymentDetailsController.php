<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Api\GatewayDetails;

use Crehler\PaymentBundle\Application\Port\Driven\GatewayPaymentDetailsProviderInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\{Autoconfigure, AutowireIterator};
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

use function sprintf;

/**
 * Admin endpoint backing the "Szczegóły płatności (bramka)" section on the order
 * details tab. An order can have several transactions (retries, payment-method
 * changes); this returns the list of those handled by a Crehler provider plus the
 * details for one (the requested transaction, or the newest by default).
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Autoconfigure(public: true)]
final class GatewayPaymentDetailsController
{
    /**
     * @param iterable<GatewayPaymentDetailsProviderInterface> $providers
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        #[AutowireIterator(GatewayPaymentDetailsProviderInterface::class)]
        private readonly iterable $providers,
    ) {
    }

    #[Route(
        path: '/api/_action/crehler-payment/order/{orderId}/gateway-details',
        name: 'api.action.crehler-payment.gateway-details',
        requirements: ['orderId' => '[0-9a-fA-F]{32}'],
        methods: ['GET'],
    )]
    public function gatewayDetails(string $orderId, Request $request, Context $context): JsonResponse
    {
        $order = $this->loadOrder($orderId, $context);

        if ($order === null) {
            return new JsonResponse(['error' => 'order_not_found'], Response::HTTP_NOT_FOUND);
        }

        foreach ($order->getTransactions() ?? [] as $orderTransaction) {
            $orderTransaction->setOrder($order);
        }

        // Build the list of order transactions a Crehler provider owns (newest first),
        // remembering which provider handles each so we can fetch details for one.
        $supported = [];
        /** @var array<string, array{0: GatewayPaymentDetailsProviderInterface, 1: OrderTransactionEntity}> $byId */
        $byId = [];

        foreach ($order->getTransactions() ?? [] as $transaction) {
            $provider = $this->resolveProvider($transaction);
            if ($provider === null) {
                continue;
            }

            $byId[$transaction->getId()] = [$provider, $transaction];
            $supported[] = [
                'orderTransactionId' => $transaction->getId(),
                'label' => $this->buildLabel($transaction),
                'createdAt' => $transaction->getCreatedAt()?->format(DATE_ATOM),
            ];
        }

        if ($supported === []) {
            return new JsonResponse(['transactions' => [], 'selected' => null, 'details' => null]);
        }

        $requested = (string) $request->query->get('transactionId', '');
        $selectedId = isset($byId[$requested]) ? $requested : $supported[0]['orderTransactionId'];

        [$provider, $transaction] = $byId[$selectedId];
        $details = $provider->getDetails($transaction);

        // Enrich amount/currency from the Shopware transaction when the gateway omits them.
        if ($details !== null && $details->amount === null) {
            $details = $details->withAmount(
                $transaction->getAmount()?->getTotalPrice(),
                $order->getCurrency()?->getIsoCode(),
            );
        }

        return new JsonResponse([
            'transactions' => $supported,
            'selected' => $selectedId,
            'details' => $details,
        ]);
    }

    private function resolveProvider(OrderTransactionEntity $transaction): ?GatewayPaymentDetailsProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($transaction)) {
                return $provider;
            }
        }

        return null;
    }

    private function buildLabel(OrderTransactionEntity $transaction): string
    {
        $date = $transaction->getCreatedAt()?->format('Y-m-d H:i') ?? '—';
        $method = $transaction->getPaymentMethod()?->getTranslation('name')
            ?? $transaction->getPaymentMethod()?->getName()
            ?? '';

        return $method !== '' ? sprintf('%s · %s', $date, $method) : $date;
    }

    private function loadOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->getAssociation('transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        return $this->orderRepository->search($criteria, $context)->first();
    }
}
