<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Adapter;

use Crehler\PaymentBundle\Domain\Contract\PaymentSubMethodPort;
use Crehler\PaymentBundle\Domain\Enum\PaymentType;
use Crehler\PaymentBundle\Infrastructure\Port\PaymentSubMethodProvider;
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentMethodContractResolver;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function in_array;

/**
 * Fans a channel-list request out to whichever providers declared the method's family.
 *
 * This is the ONLY place that decides which provider owns a payment method. Providers used
 * to answer that themselves, each with a hand-kept list of handler class names, and this
 * adapter asked the same question again right after — two copies of one rule, free to
 * drift. They did: a wallet handler named outside the naming convention and an instalments
 * family nobody had registered both resolved to "belongs to no one".
 *
 * A method whose handler declares no contract (not ours, or a row that outlived its
 * plugin) has no type, so no provider matches and the list comes back empty.
 */
final readonly class PaymentSubMethodAdapter implements PaymentSubMethodPort
{
    public function __construct(
        #[AutowireIterator(PaymentSubMethodProvider::class)]
        private iterable $subMethodProviders,
        private PaymentMethodContractResolver $contractResolver,
    ) {
    }

    public function getPaymentSubMethods(
        PaymentMethodEntity $paymentMethodEntity,
        int $paymentValue,
        SalesChannelContext $context,
    ): array {
        $type = $this->paymentType($paymentMethodEntity);

        if ($type === null) {
            return [];
        }

        $paymentSubMethods = [];

        foreach ($this->subMethodProviders as $provider) {
            if (!in_array($type, $provider->supportedPaymentTypes(), true)) {
                continue;
            }

            foreach ($provider->getPaymentSubMethods($paymentMethodEntity, $paymentValue, $context) as $subMethod) {
                $paymentSubMethods[] = $subMethod;
            }
        }

        return $paymentSubMethods;
    }

    /**
     * Whether any provider serves this method's family.
     *
     * Cheap by design — a type comparison against declared lists, no gateway call — so
     * callers may ask it per payment method while rendering a page.
     */
    public function supportsPaymentMethod(PaymentMethodEntity $paymentMethodEntity): bool
    {
        $type = $this->paymentType($paymentMethodEntity);

        if ($type === null) {
            return false;
        }

        foreach ($this->subMethodProviders as $provider) {
            if (in_array($type, $provider->supportedPaymentTypes(), true)) {
                return true;
            }
        }

        return false;
    }

    private function paymentType(PaymentMethodEntity $paymentMethodEntity): ?PaymentType
    {
        return $this->contractResolver->resolve($paymentMethodEntity)?->type;
    }
}
