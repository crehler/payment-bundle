<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Cyklicznie (co godzinę) odpytuje zarejestrowane RefundReconciliationProviderPort
 * o zwroty utworzone bezpośrednio w panelu bramki płatności, których webhook nigdy
 * nie zgłosił do Shopware (patrz WT-905 — Tpay nie wysyła żadnego powiadomienia dla
 * częściowego zwrotu panelowego).
 */
class RefundReconciliationTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'crehler_payment_bundle.reconcile_external_refunds';
    }

    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }
}
