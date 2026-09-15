<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Port;

use Crehler\PaymentBundle\Domain\Enum\PaymentType;
use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Driven port a provider implements to supply the channels behind one payment family:
 * the PBL bank list, the wallets, the BNPL options.
 *
 * A provider declares WHICH families it serves and knows HOW to fetch their channels.
 * It no longer decides WHETHER it owns a given payment method — PaymentSubMethodAdapter
 * compares the method's declared PaymentType against supportedPaymentTypes(), once.
 *
 * Before, every implementation carried its own predicate over a hand-kept list of
 * handler class names:
 *
 *     in_array($pm->getHandlerIdentifier(), self::SUPPORTED_HANDLERS, true)
 *     $pm->getHandlerIdentifier() === BankHandler::class
 *
 * Six of those, plus the same question asked a second time inside
 * AbstractPaymentSubMethodProvider. Declaring types instead means a new handler of an
 * existing family is served without touching the provider, and the subscription reads in
 * domain language rather than as a list of fully-qualified class names.
 */
#[AutoconfigureTag]
interface PaymentSubMethodProvider
{
    /**
     * The payment families whose channels this provider can fetch.
     *
     * @return list<PaymentType>
     */
    public function supportedPaymentTypes(): array;

    /**
     * Channels for the given method, already filtered to what the checkout value allows.
     *
     * Only called by the adapter, and only for a method whose type this provider declared.
     *
     * @return array<PaymentSubMethod>
     */
    public function getPaymentSubMethods(
        PaymentMethodEntity $paymentMethodEntity,
        int $paymentValue,
        SalesChannelContext $context,
    ): array;
}
