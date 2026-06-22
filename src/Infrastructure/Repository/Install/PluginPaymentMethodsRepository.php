<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Repository\Install;

use Crehler\PaymentBundle\Infrastructure\Struct\PaymentSubMethod\PaymentMethodCollection;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\ShopwarePaymentMethod;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsAnyFilter, EqualsFilter};
use Shopware\Core\Framework\Uuid\Uuid;

use function array_map;

final readonly class PluginPaymentMethodsRepository
{
    public function __construct(
        private EntityRepository $paymentMethodRepository,
    ) {
    }

    /**
     * @param array<array> $paymentMethodData
     */
    public function upsert(array $paymentMethodData, Context $context): void
    {
        $this->paymentMethodRepository->upsert($paymentMethodData, $context);
    }

    /**
     * @param array<?string> $methodsTechnicalNames
     */
    public function getPluginPaymentMethods(string $pluginId, Context $context, array $methodsTechnicalNames = []): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('pluginId', $pluginId));

        if (!empty($methodsTechnicalNames)) {
            $criteria->addFilter(new EqualsAnyFilter('technicalName', $methodsTechnicalNames));
        }

        return $this->paymentMethodRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Return missing payment methods to upsert.
     *
     * @param array<ShopwarePaymentMethod> $paymentMethods
     */
    public function getPaymentMethodsToUpsert(array $paymentMethods, Context $context): array
    {
        $technicalNames = array_map(fn (ShopwarePaymentMethod $method) => $method->technicalName, $paymentMethods);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('technicalName', $technicalNames));
        $existingPaymentMethods = $this->paymentMethodRepository->search($criteria, $context);

        $upsert = [];
        foreach ($paymentMethods as $method) {
            $existingPaymentMethod = $existingPaymentMethods->filter(
                fn (PaymentMethodEntity $paymentMethod) => $paymentMethod->getTechnicalName() === $method->technicalName
            )->first();

            $upsert[$method->technicalName] = $existingPaymentMethod?->getId() ?? Uuid::randomHex();
        }

        return $upsert;
    }

    /**
     * Sets payment methods to active or inactive state.
     *
     * @param array<ShopwarePaymentMethod> $methods
     */
    public function setPaymentMethodsActiveState(Context $context, array $methods, string $pluginId, bool $active): void
    {
        $methodsTechnicalNames = array_map(fn (ShopwarePaymentMethod $method) => $method->technicalName, $methods);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('technicalName', $methodsTechnicalNames));
        $criteria->addFilter(new EqualsFilter('pluginId', $pluginId));

        $methodsIds = $this->paymentMethodRepository->searchIds($criteria, $context)->getIds();
        $updateData = array_map(fn (string $id) => ['id' => $id, 'active' => $active], $methodsIds);

        $this->paymentMethodRepository->update($updateData, $context);
    }
}
