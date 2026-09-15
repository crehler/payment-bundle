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

use function str_contains;

/**
 * Drops the orphaned `crehler_payment_set` custom field set (WT-992).
 *
 * The set was created on every provider install by CustomFieldCreator, which was meant
 * to declare a per-payment-method custom field holding the chosen sub-method. It never
 * declared one — the class locator filtered out the sub-method classes — so shops are
 * left with an empty set visible under Settings > Custom fields, related to `customer`
 * and read by nothing.
 *
 * The sub-method choice itself lives elsewhere and is untouched by this migration:
 * the session key `crehler_payment_sub_method_{paymentMethodId}` and the customer
 * custom field `crehler_payments` written by CustomerPaymentSubMethodRepository.
 *
 * Runs in update() rather than updateDestructive() because the guard below only ever
 * deletes a set whose fields hold no data anywhere — there is nothing for an operator
 * to lose, and the acceptance criterion is that the set disappears on plugin:update.
 * If any value is found the set is left in place, so a shop that somehow used those
 * fields keeps them and can be handled by hand.
 *
 * @internal
 */
class Migration1780000004RemoveSubMethodCustomFieldSet extends MigrationStep
{
    /**
     * @var string
     */
    private const SET_NAME = 'crehler_payment_set';

    /**
     * Entities the set could ever have been attached to. `customer` is the only relation
     * CustomFieldCreator created; `order_transaction` is checked as well because the
     * fields were nominally about a payment.
     *
     * @var string[]
     */
    private const TABLES_TO_PROBE = ['customer', 'order_transaction'];

    public function getCreationTimestamp(): int
    {
        return 1780000004;
    }

    public function update(Connection $connection): void
    {
        $setId = $connection->fetchOne(
            'SELECT `id` FROM `custom_field_set` WHERE `name` = :name',
            ['name' => self::SET_NAME],
        );

        // Already gone (fresh install, or removed by hand) — nothing to do.
        if ($setId === false) {
            return;
        }

        /** @var string[] $fieldNames */
        $fieldNames = $connection->fetchFirstColumn(
            'SELECT `name` FROM `custom_field` WHERE `set_id` = :setId',
            ['setId' => $setId],
        );

        foreach ($fieldNames as $fieldName) {
            if ($this->isFieldInUse($connection, $fieldName)) {
                return;
            }
        }

        // custom_field and custom_field_set_relation both cascade on set_id.
        $connection->executeStatement(
            'DELETE FROM `custom_field_set` WHERE `id` = :setId',
            ['setId' => $setId],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function isFieldInUse(Connection $connection, string $fieldName): bool
    {
        // The field name goes into a quoted member of a JSON path, where `"` and `\` would
        // break out of that quoting. Such a name cannot be probed safely, so err towards
        // keeping the set — same conservative outcome as finding a value.
        if (str_contains($fieldName, '"') || str_contains($fieldName, '\\')) {
            return true;
        }

        $jsonPath = '$."' . $fieldName . '"';

        foreach (self::TABLES_TO_PROBE as $table) {
            // JSON_CONTAINS_PATH matches the key itself; LIKE on the raw JSON would also hit
            // the name appearing as a value under a different key, and would read `%`/`_` in
            // the name as wildcards — every Shopware field name is snake_case.
            $found = $connection->fetchOne(
                'SELECT 1 FROM `' . $table . '` WHERE JSON_CONTAINS_PATH(`custom_fields`, \'one\', :jsonPath) LIMIT 1',
                ['jsonPath' => $jsonPath],
            );

            if ($found !== false) {
                return true;
            }
        }

        return false;
    }
}
