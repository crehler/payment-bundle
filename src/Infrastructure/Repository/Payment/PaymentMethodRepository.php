<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Repository\Payment;

use Crehler\PaymentBundle\Domain\Repository\PaymentMethodRepositoryInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
    ) {
    }

    public function findById(string $paymentMethodId, SalesChannelContext $context): ?PaymentMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $paymentMethodId));

        /** @var ?PaymentMethodEntity $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context->getContext())->first();

        return $paymentMethod;
    }
}
