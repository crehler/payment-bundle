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
 * Adds `key_version` to stored cards so the card-encryption key can be rotated
 * without re-encrypting or invalidating existing rows. Existing rows default to
 * 0 (legacy kernel.secret); new rows are written with the current key version.
 *
 * @internal
 */
class Migration1780000002AddStoredCardKeyVersion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780000002;
    }

    public function update(Connection $connection): void
    {
        $columnExists = $connection->fetchOne(
            'SHOW COLUMNS FROM `crehler_payment_stored_card` LIKE :column',
            ['column' => 'key_version'],
        );

        if ($columnExists !== false) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `crehler_payment_stored_card`
                ADD COLUMN `key_version` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `device_fingerprint`'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
