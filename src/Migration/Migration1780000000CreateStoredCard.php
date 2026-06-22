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
 * @internal
 */
class Migration1780000000CreateStoredCard extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `crehler_payment_stored_card` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `token` TEXT NULL,
                `token_hash` VARCHAR(64) NULL,
                `brand` VARCHAR(50) NULL,
                `tail` VARCHAR(4) NULL,
                `expiration_month` TINYINT UNSIGNED NULL,
                `expiration_year` SMALLINT UNSIGNED NULL,
                `device_fingerprint` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.crehler_payment_stored_card.customer_id` (`customer_id`),
                INDEX `idx.crehler_payment_stored_card.sales_channel_id` (`sales_channel_id`),
                INDEX `idx.crehler_payment_stored_card.token_hash` (`token_hash`),
                UNIQUE KEY `uniq.crehler_payment_stored_card.customer_channel_token_hash`
                    (`customer_id`, `sales_channel_id`, `token_hash`),
                CONSTRAINT `fk.crehler_payment_stored_card.customer_id`
                    FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`)
                    ON DELETE CASCADE,
                CONSTRAINT `fk.crehler_payment_stored_card.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
