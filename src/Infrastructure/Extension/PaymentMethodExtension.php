<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Extension;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\{EntityExtension, FieldCollection};
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\{ApiAware, Runtime};
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;

/**
 * Extends PaymentMethodDefinition with runtime field for payment type information.
 *
 * This extension adds a `crPaymentType` field that contains boolean flags
 * indicating the payment type (BLIK, card, bank, etc.), making it easier
 * for frontend applications to identify payment methods.
 *
 * The field is populated at runtime by PaymentMethodTypeSubscriber.
 */
class PaymentMethodExtension extends EntityExtension
{
    public const EXTENSION_NAME = 'crPaymentType';

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new ObjectField(self::EXTENSION_NAME, self::EXTENSION_NAME))
                ->addFlags(new ApiAware(), new Runtime())
        );
    }

    public function getEntityName(): string
    {
        return PaymentMethodDefinition::ENTITY_NAME;
    }
}
