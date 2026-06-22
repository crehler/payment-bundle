<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Repository;

use Crehler\PaymentBundle\Domain\Contract\CurrencyRepositoryPort;
use Crehler\PaymentBundle\Domain\ValueObjects\SupportedCurrency;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\Currency\CurrencyEntity;

class CurrencyRepository implements CurrencyRepositoryPort
{
    public function __construct(
        private readonly EntityRepository $currencyRepository,
        private readonly Context $context,
    ) {
    }

    public function findByIsoCodes(array $isoCodes): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('isoCode', $isoCodes));

        $currencies = $this->currencyRepository->search($criteria, $this->context);
        $result = [];

        /** @var CurrencyEntity $currency */
        foreach ($currencies as $currency) {
            $result[] = new SupportedCurrency(isoCode: $currency->getIsoCode(), id: $currency->getId());
        }

        return $result;
    }
}
