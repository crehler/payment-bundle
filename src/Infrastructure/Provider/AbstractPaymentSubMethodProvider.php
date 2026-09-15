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
 * Base for provider channel lists (banks, wallets, BNPL).
 *
 * The bundle owns the shared shape: apply min/max amount filtering against the checkout
 * value and map raw entries to PaymentSubMethod value objects. A provider implements only
 * supportedPaymentTypes() — a declaration — and fetchRawSubMethods(), the gateway call
 * plus any gateway-specific filtering such as group or availability.
 *
 * getPaymentSubMethods() is final and no longer re-checks whether this provider owns the
 * method. PaymentSubMethodAdapter already answered that from the declared types, and
 * asking twice in a row was how the two copies of that question drifted apart.
 */
abstract class AbstractPaymentSubMethodProvider implements PaymentSubMethodProvider
{
    final public function getPaymentSubMethods(
        PaymentMethodEntity $paymentMethodEntity,
        int $paymentValue,
        SalesChannelContext $context,
    ): array {
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
     * Fetch the provider's raw channels from its gateway API. Gateway-specific filtering
     * (group exclusion, availability) belongs here; min/max amount filtering is handled
     * above.
     *
     * Populate minAmount/maxAmount whenever the gateway publishes per-channel limits —
     * that is what feeds the filter. A provider that leaves them null makes the filter
     * inert for its channels and relies entirely on the gateway having filtered already.
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
