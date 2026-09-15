<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Infrastructure\Api\GatewayDetails;

use Crehler\PaymentBundle\Application\DTO\GatewayDetails\GatewayPaymentDetails;
use Crehler\PaymentBundle\Application\Port\Driven\GatewayPaymentDetailsProviderInterface;
use Crehler\PaymentBundle\Infrastructure\Api\GatewayDetails\GatewayPaymentDetailsController;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionCollection, OrderTransactionEntity};
use Shopware\Core\Checkout\Order\{OrderCollection, OrderEntity};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\{Criteria, EntitySearchResult};
use Symfony\Component\HttpFoundation\Request;

/**
 * Every provider in the family opens getDetails() with
 *
 *     $salesChannelId = $orderTransaction->getOrder()?->getSalesChannelId();
 *
 * and the DAL does not populate an inverse association: loading order.transactions sets
 * each transaction's orderId and leaves getOrder() null. So all four read null, fell back
 * to the global configuration, and on a shop with per-sales-channel credentials would ask
 * the wrong merchant — or the wrong environment — and report no transaction for an order
 * that is paid.
 *
 * It stayed invisible from 2026-06-18 until now because the config service falls back to
 * the global value, and every shop that had this panel was configured globally.
 */
final class GatewayPaymentDetailsControllerTest extends TestCase
{
    private const ORDER_ID = '01a08f4a3a0d71a69e4c8dbd344e6d65';
    private const TRANSACTION_ID = '01a08f4a3a0d71a69e4c8dbd33c33c8e';
    private const SALES_CHANNEL_ID = '019ed035634570b09e40580c5ba4654a';

    public function testTheProviderReceivesATransactionThatKnowsItsOrder(): void
    {
        $provider = $this->recordingProvider();

        $this->controller($provider)->gatewayDetails(self::ORDER_ID, new Request(), Context::createDefaultContext());

        self::assertNotNull($provider->seen, 'the provider was never asked');
        self::assertNotNull(
            $provider->seen->getOrder(),
            'the provider got a transaction with no order, so it cannot tell which sales channel to use',
        );
        self::assertSame(self::SALES_CHANNEL_ID, $provider->seen->getOrder()?->getSalesChannelId());
    }

    /**
     * supports() runs before getDetails() and decides whether a provider is asked at all,
     * so a provider that keys off the sales channel must not be handed a bare transaction
     * there either.
     */
    public function testTheRelationIsAlreadyThereWhenSupportsIsCalled(): void
    {
        $provider = $this->recordingProvider();

        $this->controller($provider)->gatewayDetails(self::ORDER_ID, new Request(), Context::createDefaultContext());

        self::assertNotNull($provider->seenBySupports?->getOrder());
    }

    public function testAMissingOrderStillAnswers404(): void
    {
        $controller = new GatewayPaymentDetailsController($this->emptyRepository(), [$this->recordingProvider()]);

        $response = $controller->gatewayDetails(self::ORDER_ID, new Request(), Context::createDefaultContext());

        self::assertSame(404, $response->getStatusCode());
    }

    private function controller(GatewayPaymentDetailsProviderInterface $provider): GatewayPaymentDetailsController
    {
        return new GatewayPaymentDetailsController($this->repositoryReturningOrder(), [$provider]);
    }

    private function recordingProvider(): GatewayPaymentDetailsProviderInterface
    {
        return new class implements GatewayPaymentDetailsProviderInterface {
            public ?OrderTransactionEntity $seen = null;

            public ?OrderTransactionEntity $seenBySupports = null;

            public function supports(OrderTransactionEntity $orderTransaction): bool
            {
                $this->seenBySupports = $orderTransaction;

                return true;
            }

            public function getDetails(OrderTransactionEntity $orderTransaction): ?GatewayPaymentDetails
            {
                $this->seen = $orderTransaction;

                return new GatewayPaymentDetails(
                    provider: 'test',
                    gatewayId: 'a0256c4d-532b-4418-8b9d-684ef561bd5a',
                    rawStatus: 'settled',
                    statusLevel: 'paid',
                    sandbox: true,
                    amount: 495.95,
                    currency: 'PLN',
                );
            }
        };
    }

    private function repositoryReturningOrder(): EntityRepository
    {
        // Built the way the DAL hands it over: the transaction carries its orderId, and
        // getOrder() is null until something puts the entity there.
        $transaction = new OrderTransactionEntity();
        $transaction->setId(self::TRANSACTION_ID);
        $transaction->setOrderId(self::ORDER_ID);

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setSalesChannelId(self::SALES_CHANNEL_ID);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        self::assertNull($transaction->getOrder(), 'fixture must start from the DAL shape, or it proves nothing');

        return $this->repositoryReturning(new OrderCollection([$order]));
    }

    private function emptyRepository(): EntityRepository
    {
        return $this->repositoryReturning(new OrderCollection());
    }

    private function repositoryReturning(OrderCollection $orders): EntityRepository
    {
        $result = new EntitySearchResult(
            'order',
            $orders->count(),
            $orders,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        return $repository;
    }
}
