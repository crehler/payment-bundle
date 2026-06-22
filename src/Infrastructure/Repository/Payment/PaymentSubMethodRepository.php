<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Repository\Payment;

use Crehler\PaymentBundle\Domain\Repository\PaymentSubMethodRepositoryInterface;
use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;
use Crehler\PaymentBundle\Infrastructure\StoreApi\PaymentSubMethods\PaymentSubMethodProviderInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class PaymentSubMethodRepository implements PaymentSubMethodRepositoryInterface
{
    /**
     * @param iterable<PaymentSubMethodProviderInterface> $submethodProviders
     */
    public function __construct(
        #[AutowireIterator(PaymentSubMethodProviderInterface::class)]
        private readonly iterable $submethodProviders,
    ) {
    }

    public function findByPaymentMethod(PaymentMethodEntity $paymentMethod, SalesChannelContext $context): array
    {
        $result = [];

        foreach ($this->submethodProviders as $submethodProvider) {
            if ($submethodProvider->operated($paymentMethod)) {
                $collection = $submethodProvider->getSubMethods($paymentMethod, $context);

                /** @var PaymentSubMethod $struct */
                foreach ($collection as $struct) {
                    $result[] = new PaymentSubMethod(
                        $struct->id,
                        $struct->name,
                        $struct->methodId,
                        $struct->mediaUrl,
                    );
                }

                break;
            }
        }

        return $result;
    }

    public function supportsPaymentMethod(PaymentMethodEntity $paymentMethod): bool
    {
        foreach ($this->submethodProviders as $submethodProvider) {
            if ($submethodProvider->operated($paymentMethod)) {
                return true;
            }
        }

        return false;
    }
}
