<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Domain\Contract\{CurrencyRepositoryPort, CurrencyRulePort, RuleRepositoryPort};
use Crehler\PaymentBundle\Domain\Entity\PaymentRule;
use Crehler\PaymentBundle\Domain\Port\PaymentGatewayCurrencyProviderInterface;

final readonly class CurrencyRuleService implements CurrencyRulePort
{
    public function __construct(
        private CurrencyRepositoryPort $currencyRepository,
        private RuleRepositoryPort $ruleRepository,
    ) {
    }

    public function createOrUpdateCurrencyRule(object $currencyProvider): ?string
    {
        if (!$currencyProvider instanceof PaymentGatewayCurrencyProviderInterface) {
            return null;
        }

        $currencyIsoCodes = $currencyProvider->getSupportedCurrencyIsoCodes();

        $currencies = $this->currencyRepository->findByIsoCodes($currencyIsoCodes);

        $rule = new PaymentRule(
            id: $currencyProvider->getRuleId(),
            name: $currencyProvider->getTranslations()['en-GB']['name'] ?? 'Supported currencies',
            priority: 100,
            description: $currencyProvider->getTranslations()['en-GB']['description'] ?? 'Supported currencies for payment gateway',
            translations: $currencyProvider->getTranslations(),
            supportedCurrencies: $currencies
        );

        $this->ruleRepository->upsert($rule);

        return $rule->id;
    }
}
