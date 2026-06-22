<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install;

use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;

abstract class SubPaymentCustomFieldCreator
{
    /**
     * @var string
     */
    public const PAYMENT_SUB_METHOD = 'crehler_payment_submethod_';

    /**
     * Return array with configuration of custom field,.
     */
    public function config(CustomFieldSetEntity $customFieldSetEntity, string $paymentMethodId): ?array
    {
        // Example of config override this config !
        return [
            'customFieldSetId' => $customFieldSetEntity->getId(),
            'name' => self::PAYMENT_SUB_METHOD . $paymentMethodId,
            'type' => 'int',
            'config' => [
                'componentName' => 'sw-field',
                'type' => 'number',
                'numberType' => 'int',
                'customFieldType' => 'number',
                'customFieldPosition' => 1,
                'label' => [
                    'en-GB' => 'En translation',
                    'pl-PL' => 'Pl translation',
                    'de-DE' => 'De translation',
                ],
            ],
            'allowCustomerWrite' => true,
        ];
    }

    /**
     * Get custom field id if exists.
     */
    public function getCustomFieldId(string $paymentMethodId, ?CustomFieldSetEntity $customFieldSetEntity): ?string
    {
        $customFieldSet = $customFieldSetEntity?->getCustomFields();

        if (empty($customFieldSet?->getElements())) {
            return null;
        }

        return $customFieldSet->filter(function ($customField) use ($paymentMethodId) {
            return $customField->getName() === self::CUSTOMER_SELECTED_BANK . $paymentMethodId;
        })->first()?->getId();
    }
}
