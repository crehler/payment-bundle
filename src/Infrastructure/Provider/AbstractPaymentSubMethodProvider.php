<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Provider;

use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;
use Crehler\PaymentBundle\Infrastructure\Port\PaymentSubMethodProvider;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Base for provider sub-method lists (banks, wallets, BNPL).
 *
 * The bundle owns the shared shape: short-circuit when the payment method is
 * not supported, apply min/max amount filtering against the checkout value and
 * map raw entries to PaymentSubMethod value objects. A provider implements only
 * supportsPaymentMethod() and fetchRawSubMethods() (the gateway API call plus
 * any gateway-specific filtering such as group/availability).
 */
abstract class AbstractPaymentSubMethodProvider implements PaymentSubMethodProvider
{
    final public function getPaymentSubMethods(
        PaymentMethodEntity $paymentMethodEntity,
        int $paymentValue,
        SalesChannelContext $context,
    ): array {
        if (!$this->supportsPaymentMethod($paymentMethodEntity)) {
            return [];
        }

        $subMethods = [];

        foreach ($this->fetchRawSubMethods($paymentMethodEntity, $paymentValue, $context) as $raw) {
            if ($this->isOutsideAmountRange($raw, $paymentValue)) {
                continue;
            }

            $subMethods[] = new PaymentSubMethod(
                providerId: $raw->providerId,
                name: $raw->name,
                shopwareId: $paymentMethodEntity->getId(),
                mediaUrl: $raw->mediaUrl,
                minAmount: $raw->minAmount,
                maxAmount: $raw->maxAmount,
            );
        }

        return $subMethods;
    }

    /**
     * Fetch the provider's raw sub-methods from its gateway API. Gateway-specific
     * filtering (group exclusion, availability) belongs here; min/max amount
     * filtering is handled by the bundle.
     *
     * @return iterable<RawSubMethod>
     */
    abstract protected function fetchRawSubMethods(
        PaymentMethodEntity $paymentMethodEntity,
        int $paymentValue,
        SalesChannelContext $context,
    ): iterable;

    private function isOutsideAmountRange(RawSubMethod $raw, int $paymentValue): bool
    {
        if ($raw->minAmount !== null && $paymentValue < $raw->minAmount) {
            return true;
        }

        return $raw->maxAmount !== null && $paymentValue > $raw->maxAmount;
    }
}
