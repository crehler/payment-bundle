<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Infrastructure\Repository\Order;

use Crehler\PaymentBundle\Infrastructure\Repository\Order\OrderTransactionRepository;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionCollection, OrderTransactionEntity, OrderTransactionStateHandler};
use Shopware\Core\Checkout\Order\{OrderCollection, OrderEntity};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * A retry has to pay what the customer still owes, which is the order's primary transaction.
 * The most recent transaction is not that whenever something else was booked after it: a
 * part of the order settled outside the gateway is created later and is already paid, so
 * copying it sent the retry to a handler that never reaches the gateway — the retry failed
 * and its failed copy became the order's primary.
 */
final class OrderTransactionRepositoryTest extends TestCase
{
    private const ORDER_ID = '0190c3f1a6e47a2b8f5d1e9c3b7a4d21';
    private const GATEWAY_METHOD_ID = '0190c3f1a6e47a2b8f5d1e9c3b7a4d22';
    private const OTHER_METHOD_ID = '0190c3f1a6e47a2b8f5d1e9c3b7a4d23';

    public function testRetryCopiesThePrimaryTransactionEvenWhenANewerOneExists(): void
    {
        $primary = $this->transaction(self::GATEWAY_METHOD_ID, 175.01);

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setPrimaryOrderTransaction($primary);

        // A newer transaction the old "most recent" lookup would have picked instead.
        $newer = $this->transaction(self::OTHER_METHOD_ID, 100.0);

        $orders = new StaticEntityRepository([new OrderCollection([$order])]);
        $transactions = new StaticEntityRepository([
            fn (Criteria $criteria): OrderTransactionCollection => $criteria->getIds() === []
                ? new OrderTransactionCollection([$newer])
                : $this->readBack($criteria),
            $this->readBack(...),
        ]);

        $this->repository($transactions, $orders)->createRetryTransactionForOrder(self::ORDER_ID, Context::createDefaultContext());

        $created = $this->createdTransaction($orders);
        static::assertSame(self::GATEWAY_METHOD_ID, $created['paymentMethodId']);
        static::assertSame(175.01, $created['amount']->getTotalPrice());
    }

    public function testRetryFallsBackToTheMostRecentTransactionWithoutAPrimary(): void
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);

        $orders = new StaticEntityRepository([new OrderCollection([$order])]);
        $transactions = new StaticEntityRepository([
            function (Criteria $criteria): OrderTransactionCollection {
                $sorting = $criteria->getSorting();
                static::assertCount(1, $sorting);
                static::assertSame('createdAt', $sorting[0]->getField());
                static::assertSame(FieldSorting::DESCENDING, $sorting[0]->getDirection());

                return new OrderTransactionCollection([$this->transaction(self::OTHER_METHOD_ID, 275.01)]);
            },
            $this->readBack(...),
        ]);

        $this->repository($transactions, $orders)->createRetryTransactionForOrder(self::ORDER_ID, Context::createDefaultContext());

        $created = $this->createdTransaction($orders);
        static::assertSame(self::OTHER_METHOD_ID, $created['paymentMethodId']);
        static::assertSame(275.01, $created['amount']->getTotalPrice());
    }

    /**
     * @param StaticEntityRepository<OrderTransactionCollection> $transactions
     * @param StaticEntityRepository<OrderCollection> $orders
     */
    private function repository(StaticEntityRepository $transactions, StaticEntityRepository $orders): OrderTransactionRepository
    {
        $initialStateIdLoader = $this->createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn(Uuid::randomHex());

        return new OrderTransactionRepository(
            $transactions,
            $orders,
            $initialStateIdLoader,
            $this->createStub(OrderTransactionStateHandler::class),
        );
    }

    /**
     * @param StaticEntityRepository<OrderCollection> $orders
     *
     * @return array<string, mixed>
     */
    private function createdTransaction(StaticEntityRepository $orders): array
    {
        static::assertCount(1, $orders->updates);
        $payload = $orders->updates[0][0];
        static::assertSame($payload['transactions'][0]['id'], $payload['primaryOrderTransactionId']);

        return $payload['transactions'][0];
    }

    private function readBack(Criteria $criteria): OrderTransactionCollection
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId($criteria->getIds()[0]);

        return new OrderTransactionCollection([$transaction]);
    }

    private function transaction(string $paymentMethodId, float $total): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setAmount(new CalculatedPrice($total, $total, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $transaction;
    }
}
