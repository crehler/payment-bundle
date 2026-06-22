<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install;

use Crehler\PaymentBundle\Infrastructure\Enum\{CustomFieldsEnum, PaymentDirectoriesPathEnum};
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\InstallInterface;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\PluginHelperAwareTrait;
use JetBrains\PhpStorm\NoReturn;
use ReflectionException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomFieldCreator implements InstallInterface
{
    use PluginHelperAwareTrait;

    /**
     * @var string
     */
    public const CUSTOMER_SET_NAME = 'customer';

    private readonly EntityRepository $customFieldSetRepository;
    private readonly EntityRepository $customFieldRepository;
    private EntityRepository $paymentMethodRepository;

    public function __construct(
        protected readonly ContainerInterface $container,
    ) {
        $this->customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $this->customFieldRepository = $this->container->get('custom_field.repository');
        $this->paymentMethodRepository = $this->container->get('payment_method.repository');
    }

    /**
     * @throws ReflectionException
     */
    #[NoReturn]
    public function install(InstallContext $context, string $pluginId, ?string $baseClass): void
    {
        $paymentsMethods = $this->getPaymentMethodClassLocator()->getMethods(
            baseClass: $baseClass,
            pathToFolder: PaymentDirectoriesPathEnum::PAYMENT_METHODS
        );

        if (empty($paymentsMethods)) {
            return;
        }

        foreach ($paymentsMethods as $paymentMethod) {
            if (!$paymentMethod->subMethodsEnabled) {
                continue;
            }

            $paymentMethodId = $this->getPaymentMethod(
                context: $context->getContext(),
                paymentTechnicalName: $paymentMethod->technicalName
            )->getId();

            $this->setCustomFieldsSet(context: $context->getContext(), pluginId: $pluginId, baseClass: $baseClass, patmentMethodId: $paymentMethodId);
        }
    }

    public function getPaymentMethod(Context $context, string $paymentTechnicalName): PaymentMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $paymentTechnicalName));

        return $this->paymentMethodRepository->search($criteria, $context)->first();
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    private function setCustomFieldsSet(Context $context, string $pluginId, string $baseClass, string $patmentMethodId): void
    {
        $customFieldSet = $this->getOrSetCustomFieldSet(context: $context);

        $this->setCustomFields(
            context: $context,
            customFieldSetEntity: $customFieldSet,
            baseClass: $baseClass,
            paymentMethodId: $patmentMethodId
        );
    }

    private function setCustomFields(
        Context $context,
        CustomFieldSetEntity $customFieldSetEntity,
        string $baseClass,
        string $paymentMethodId,
    ): void {
        $data = $this->getCustomFieldsToInsert(
            baseClass: $baseClass,
            customFieldSetEntity: $customFieldSetEntity,
            paymentMethodId: $paymentMethodId);

        if (empty($data)) {
            return;
        }

        $this->customFieldRepository->upsert($data, $context);
    }

    private function getCustomFieldsToInsert(string $baseClass, CustomFieldSetEntity $customFieldSetEntity, string $paymentMethodId): array
    {
        $subMethods = $this->getPaymentMethodClassLocator()->getMethods(
            baseClass: $baseClass,
            pathToFolder: PaymentDirectoriesPathEnum::SUBPAYMENT_METHODS
        );

        $configArr = [];

        foreach ($subMethods as $subMethod) {
            /** @var SubPaymentCustomFieldCreator $subMethod */
            $customFieldConfig = (new $subMethod())->config(customFieldSetEntity: $customFieldSetEntity, paymentMethodId: $paymentMethodId);
            if ($customFieldConfig === null) {
                continue;
            }

            $configArr[] = $customFieldConfig;
        }

        return $configArr;
    }

    private function getOrSetCustomFieldSet(Context $context): CustomFieldSetEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', CustomFieldsEnum::CUSTOM_FIELD_SET->value));
        $criteria->addAssociations([
            'customFields',
            'relations',
        ]);

        /** @var CustomFieldSetEntity|null $customFieldSet */
        $customFieldSet = $this->customFieldSetRepository->search($criteria, $context)->first();

        if ($customFieldSet == null) {
            $data = [
                'name' => CustomFieldsEnum::CUSTOM_FIELD_SET->value,
                'config' => [
                    'label' => [
                        'en-GB' => 'Crehler payment bundle set',
                        'de-DB' => 'Crehler payment bundle set',
                        'pl-PL' => 'Crehler payment bundle set',
                    ],
                ],
                'relations' => [
                    [
                        'id' => $this->getOrSetCustomFieldRelationId(customFieldSet: null),
                        'entityName' => self::CUSTOMER_SET_NAME,
                    ],
                ],
            ];

            $this->customFieldSetRepository->create([$data], $context);

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', $data['name']));

            return $this->customFieldSetRepository
                ->search(criteria: $criteria, context: $context)
                ->first();
        }

        return $customFieldSet;
    }

    private function getOrSetCustomFieldRelationId(?CustomFieldSetEntity $customFieldSet): string
    {
        return $customFieldSet?->getRelations()->filter(function ($relation) {
            return $relation->getEntityName() === self::CUSTOMER_SET_NAME;
        })->first()?->getId() ?? Uuid::randomHex();
    }
}
