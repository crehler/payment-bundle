<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity;

use Crehler\PaymentBundle\Domain\ValueObjects\SupportedCurrency;
use Shopware\Core\Framework\Uuid\Uuid;

use function array_map;

final readonly class PaymentRule
{
    public function __construct(
        public string $id,
        public string $name,
        public int $priority,
        public string $description,
        public array $translations = [],
        public array $supportedCurrencies = [],
    ) {
    }

    public function toArray(): array
    {
        $currencyIds = array_map(
            fn (SupportedCurrency $currency) => $currency->id,
            $this->supportedCurrencies
        );

        $condition1Id = Uuid::randomHex();
        $condition2Id = Uuid::randomHex();
        $condition3Id = Uuid::randomHex();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'priority' => $this->priority,
            'description' => $this->description,
            'translations' => $this->translations,
            'moduleTypes' => [
                'types' => ['payment'],
            ],
            'conditions' => [
                [
                    'id' => $condition1Id,
                    'type' => 'orContainer',
                    'ruleId' => $this->id,
                    'value' => [],
                    'position' => 0,
                    'children' => [
                        [
                            'id' => $condition2Id,
                            'type' => 'andContainer',
                            'ruleId' => $this->id,
                            'parentId' => $condition1Id,
                            'value' => [],
                            'position' => 0,
                            'children' => [
                                [
                                    'id' => $condition3Id,
                                    'type' => 'currency',
                                    'ruleId' => $this->id,
                                    'parentId' => $condition2Id,
                                    'value' => [
                                        'operator' => '=',
                                        'currencyIds' => $currencyIds,
                                    ],
                                    'position' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
