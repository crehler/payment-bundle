<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install;

use Crehler\PaymentBundle\Domain\Constant\PaymentCustomFields;
use Crehler\PaymentBundle\Infrastructure\Enum\CustomFieldsEnum;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\{InstallInterface, UpdateInterface};
use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Context\{InstallContext, UpdateContext};
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a formal "Pochodzenie zwrotu" (shop / gateway_sync) select field on
 * order_transaction_capture_refund, replacing the previous bare string in
 * RefundSynchronizer's reason field with a queryable/reportable custom field. Global —
 * not per payment method — so it only needs to run once regardless of which provider
 * plugin triggers install/update first; re-running on every provider install/update is
 * a cheap no-op (guarded by name lookups).
 */
final class RefundOriginCustomFieldInstaller implements InstallInterface, UpdateInterface
{
    private const REFUND_ENTITY_NAME = 'order_transaction_capture_refund';

    private readonly EntityRepository $customFieldSetRepository;
    private readonly EntityRepository $customFieldRepository;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        $this->customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $this->customFieldRepository = $this->container->get('custom_field.repository');
    }

    public function install(InstallContext $context, string $pluginId, string $baseClass): void
    {
        $this->ensureCustomField($context->getContext());
    }

    public function update(UpdateContext $context, string $pluginId, string $baseClass): void
    {
        $this->ensureCustomField($context->getContext());
    }

    private function ensureCustomField(Context $context): void
    {
        $customFieldSet = $this->getOrCreateCustomFieldSet($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', PaymentCustomFields::REFUND_ORIGIN));

        if ($this->customFieldRepository->searchIds($criteria, $context)->firstId() !== null) {
            return;
        }

        $this->customFieldRepository->create([[
            'name' => PaymentCustomFields::REFUND_ORIGIN,
            'type' => 'select',
            'customFieldSetId' => $customFieldSet->getId(),
            'config' => [
                'label' => [
                    'en-GB' => 'Refund origin',
                    'de-DE' => 'Herkunft der Rückerstattung',
                    'pl-PL' => 'Pochodzenie zwrotu',
                ],
                'componentName' => 'sw-single-select',
                'customFieldType' => 'select',
                'customFieldPosition' => 1,
                'options' => [
                    [
                        'value' => 'shop',
                        'label' => [
                            'en-GB' => 'Initiated in shop',
                            'de-DE' => 'Im Shop initiiert',
                            'pl-PL' => 'Zainicjowany w sklepie',
                        ],
                    ],
                    [
                        'value' => 'gateway_sync',
                        'label' => [
                            'en-GB' => 'Synchronized from gateway',
                            'de-DE' => 'Aus dem Gateway synchronisiert',
                            'pl-PL' => 'Zsynchronizowany z bramki',
                        ],
                    ],
                ],
            ],
        ]], $context);
    }

    private function getOrCreateCustomFieldSet(Context $context): CustomFieldSetEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', CustomFieldsEnum::CUSTOM_FIELD_SET_REFUND->value));
        $criteria->addAssociations(['relations']);

        $customFieldSet = $this->customFieldSetRepository->search($criteria, $context)->first();

        if ($customFieldSet instanceof CustomFieldSetEntity) {
            return $customFieldSet;
        }

        $this->customFieldSetRepository->create([[
            'name' => CustomFieldsEnum::CUSTOM_FIELD_SET_REFUND->value,
            'config' => [
                'label' => [
                    'en-GB' => 'Crehler payment refund set',
                    'de-DE' => 'Crehler payment refund set',
                    'pl-PL' => 'Crehler payment refund set',
                ],
            ],
            'relations' => [
                ['entityName' => self::REFUND_ENTITY_NAME],
            ],
        ]], $context);

        $refetchCriteria = new Criteria();
        $refetchCriteria->addFilter(new EqualsFilter('name', CustomFieldsEnum::CUSTOM_FIELD_SET_REFUND->value));

        $created = $this->customFieldSetRepository->search($refetchCriteria, $context)->first();

        if (!$created instanceof CustomFieldSetEntity) {
            throw new RuntimeException('Failed to read back the just-created ' . CustomFieldsEnum::CUSTOM_FIELD_SET_REFUND->value . ' custom field set.');
        }

        return $created;
    }
}
