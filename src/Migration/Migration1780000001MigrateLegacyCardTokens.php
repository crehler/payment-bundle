<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Copies card tokens from the legacy per-plugin tables into the shared
 * crehler_payment_stored_card table. Idempotent (INSERT IGNORE, keeps ids) and
 * safe when a legacy table is absent. Legacy tables are left in place; dropping
 * them is a separate cleanup step.
 *
 * @internal
 */
class Migration1780000001MigrateLegacyCardTokens extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780000001;
    }

    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();

        if ($schemaManager->tablesExist(['crehler_tpay_saved_card'])) {
            $connection->executeStatement('
                INSERT IGNORE INTO `crehler_payment_stored_card`
                    (`id`, `customer_id`, `sales_channel_id`, `token`, `token_hash`,
                     `brand`, `tail`, `expiration_month`, `expiration_year`, `created_at`, `updated_at`)
                SELECT `id`, `customer_id`, `sales_channel_id`, `token`, `token_hash`,
                       `brand`, `tail`, `expiration_month`, `expiration_year`, `created_at`, `updated_at`
                FROM `crehler_tpay_saved_card`;
            ');
        }

        if ($schemaManager->tablesExist(['paynow_customer_card_token'])) {
            $connection->executeStatement('
                INSERT IGNORE INTO `crehler_payment_stored_card`
                    (`id`, `customer_id`, `device_fingerprint`, `created_at`, `updated_at`)
                SELECT `id`, `customer_id`, `device_fingerprint`, `created_at`, `updated_at`
                FROM `paynow_customer_card_token`;
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
