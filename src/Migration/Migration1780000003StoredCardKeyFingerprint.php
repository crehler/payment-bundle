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
 * Replaces the short-lived `key_version` column with `key_fingerprint`: stored card
 * tokens are now encrypted with a dedicated key kept in the private filesystem, and
 * each row records a fingerprint of the key it was encrypted with so rows under a
 * lost/rotated key can be pruned.
 *
 * @internal
 */
class Migration1780000003StoredCardKeyFingerprint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780000003;
    }

    public function update(Connection $connection): void
    {
        $hasFingerprint = $connection->fetchOne(
            'SHOW COLUMNS FROM `crehler_payment_stored_card` LIKE :column',
            ['column' => 'key_fingerprint'],
        );

        if ($hasFingerprint === false) {
            $connection->executeStatement(
                'ALTER TABLE `crehler_payment_stored_card`
                    ADD COLUMN `key_fingerprint` VARCHAR(64) NULL AFTER `device_fingerprint`'
            );
        }

        $hasVersion = $connection->fetchOne(
            'SHOW COLUMNS FROM `crehler_payment_stored_card` LIKE :column',
            ['column' => 'key_version'],
        );

        if ($hasVersion !== false) {
            $connection->executeStatement(
                'ALTER TABLE `crehler_payment_stored_card` DROP COLUMN `key_version`'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
