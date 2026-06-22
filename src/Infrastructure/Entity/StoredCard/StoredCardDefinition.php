<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Entity\StoredCard;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\{EntityDefinition, FieldCollection};
use Shopware\Core\Framework\DataAbstractionLayer\Field\{
    FkField,
    IdField,
    IntField,
    LongTextField,
    ManyToOneAssociationField,
    StringField
};
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\{PrimaryKey, Required};
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class StoredCardDefinition extends EntityDefinition
{
    /**
     * @var string
     */
    public const ENTITY_NAME = 'crehler_payment_stored_card';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StoredCardEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StoredCardCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required()),
            new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class),
            new LongTextField('token', 'token'),
            new StringField('token_hash', 'tokenHash'),
            new StringField('brand', 'brand'),
            new StringField('tail', 'tail'),
            new IntField('expiration_month', 'expirationMonth'),
            new IntField('expiration_year', 'expirationYear'),
            new StringField('device_fingerprint', 'deviceFingerprint'),
            new StringField('key_fingerprint', 'keyFingerprint', 64),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
