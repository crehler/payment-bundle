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
 * Adds the crPaymentContract runtime field to payment_method.
 *
 * Carries PaymentMethodContractStruct: the payment family and whether the method's
 * channels come from the gateway, both declared by the handler. ApiAware, so storefront
 * templates and Store API consumers read the same answer.
 *
 * Populated on entity load by PaymentMethodTypeSubscriber.
 */
class PaymentMethodExtension extends EntityExtension
{
    public const EXTENSION_NAME = 'crPaymentContract';

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
