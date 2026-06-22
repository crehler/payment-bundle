<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util;

use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\{ShopwarePaymentMethod, ShopwarePaymentMethodDescription};
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

use function array_key_first;
use function count;

final readonly class PaymentMethodDataMapper
{
    public function __construct(
        private readonly EntityRepository $languageRepository,
        private readonly PaymentMethodMediaManager $mediaManager,
        private readonly string $baseClass,
    ) {
    }

    public function map(array $methods, array $identifiers, string $pluginId, ?string $ruleId, Context $context): array
    {
        $defaultLanguage = $this->getDefaultLanguage($context);
        $upsertData = [];

        /** @var ShopwarePaymentMethod $method */
        foreach ($methods as $method) {
            if (count($method->translations) === 0) {
                continue;
            }

            $defaultTranslation = $this->getDefaultTranslation($method->translations, $defaultLanguage);

            $item = [
                'id' => $identifiers[$method->technicalName],
                'handlerIdentifier' => $method->handlerIdentifier,
                'active' => false,
                'name' => $defaultTranslation->name,
                'description' => $defaultTranslation->description,
                'translations' => [],
                'pluginId' => $pluginId,
                'afterOrderEnabled' => $method->afterOrderEnabled,
                'availabilityRuleId' => $ruleId,
                'technicalName' => $method->technicalName,
            ];

            if ($method->iconName !== null) {
                $mediaId = $this->mediaManager->getPaymentMethodIcon(
                    $identifiers[$method->technicalName],
                    $method->iconName,
                    $this->baseClass,
                    $context
                );
                if ($mediaId !== null) {
                    $item['media'] = [
                        'id' => $mediaId,
                        'mediaFolderId' => $this->mediaManager->getMediaDefaultFolderId($context),
                    ];
                }
            }

            /** @var ShopwarePaymentMethodDescription $translation */
            foreach ($method->translations as $translation) {
                $item['translations'][$translation->language] = [
                    'name' => $translation->name,
                    'description' => $translation->description,
                ];
            }
            $upsertData[] = $item;
        }

        return $upsertData;
    }

    private function getDefaultLanguage(Context $context): string
    {
        $criteria = new Criteria([Defaults::LANGUAGE_SYSTEM]);
        $criteria->addAssociation('locale');

        return $this->languageRepository->search($criteria, $context)
            ->first()
            ->getLocale()
            ->getCode();
    }

    private function getDefaultTranslation(array $translations, string $defaultLanguage): ShopwarePaymentMethodDescription
    {
        $defaultTranslation = $translations[array_key_first($translations)];

        /** @var ShopwarePaymentMethodDescription $translation */
        foreach ($translations as $translation) {
            if ($translation->language === $defaultLanguage) {
                return $translation;
            }
        }

        return $defaultTranslation;
    }
}
