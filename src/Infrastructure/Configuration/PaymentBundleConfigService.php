<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Configuration;

use Crehler\PaymentBundle\PaymentPluginBootstrap;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function count;
use function explode;
use function str_starts_with;

/**
 * Service for accessing shared payment bundle configuration.
 * Resolves configuration values based on payment method's provider plugin.
 */
final readonly class PaymentBundleConfigService
{
    /**
     * @param array<string, string> $bundles Kernel bundles [name => class]
     */
    public function __construct(
        private SystemConfigService $systemConfigService,
        #[Autowire(param: 'kernel.bundles')]
        private array $bundles,
    ) {
    }

    /**
     * Check if card form should be embedded in checkout for given payment method.
     */
    public function isEmbedCardFormEnabled(PaymentMethodEntity $paymentMethod, ?string $salesChannelId = null): bool
    {
        $configDomain = $this->resolveConfigDomain($paymentMethod);

        if ($configDomain === null) {
            return false;
        }

        return (bool) $this->systemConfigService->get(
            $configDomain . '.' . BundleConfigField::EMBED_CARD_FORM->value,
            $salesChannelId
        );
    }

    /**
     * Get BLIK input position for given payment method.
     *
     * @return string One of: 'checkout', 'separate', 'hidden'
     */
    public function getBlikInputPosition(PaymentMethodEntity $paymentMethod, ?string $salesChannelId = null): string
    {
        $handlerIdentifier = $paymentMethod->getHandlerIdentifier();

        return $handlerIdentifier === null
            ? 'checkout'
            : $this->getBlikInputPositionForHandler($handlerIdentifier, $salesChannelId);
    }

    /**
     * Same as getBlikInputPosition(), for callers that only have the handler identifier
     * (e.g. the lightweight domain PaymentMethod value object used inside payment handlers)
     * rather than a full native PaymentMethodEntity.
     *
     * @return string One of: 'checkout', 'separate', 'hidden'
     */
    public function getBlikInputPositionForHandler(string $handlerIdentifier, ?string $salesChannelId = null): string
    {
        $configDomain = $this->resolveConfigDomainForHandlerIdentifier($handlerIdentifier);

        if ($configDomain === null) {
            return 'checkout';
        }

        $value = $this->systemConfigService->get(
            $configDomain . '.' . BundleConfigField::BLIK_INPUT_POSITION->value,
            $salesChannelId
        );

        return $value ?: 'checkout';
    }

    /**
     * Get any bundle config value for given payment method.
     */
    public function getConfigValue(
        PaymentMethodEntity $paymentMethod,
        BundleConfigField $field,
        ?string $salesChannelId = null,
    ): mixed {
        $configDomain = $this->resolveConfigDomain($paymentMethod);

        if ($configDomain === null) {
            return null;
        }

        return $this->systemConfigService->get(
            $configDomain . '.' . $field->value,
            $salesChannelId
        );
    }

    /**
     * Resolve configuration domain from payment method's handler.
     * Extracts plugin name from handler namespace.
     *
     * Example: Crehler\PayU\Handler\CardHandler -> CrehlerPayUPayment.config
     */
    private function resolveConfigDomain(PaymentMethodEntity $paymentMethod): ?string
    {
        $handlerIdentifier = $paymentMethod->getHandlerIdentifier();

        return $handlerIdentifier === null
            ? null
            : $this->resolveConfigDomainForHandlerIdentifier($handlerIdentifier);
    }

    private function resolveConfigDomainForHandlerIdentifier(string $handlerIdentifier): ?string
    {
        // Extract namespace parts (e.g., "Crehler\PayU\Handler\CardHandler")
        $parts = explode('\\', $handlerIdentifier);

        if (count($parts) < 2) {
            return null;
        }

        // Try to find matching plugin by namespace prefix
        // Crehler\PayU -> CrehlerPayUPayment, Crehler\PayNowPayment -> CrehlerPayNowPayment
        $vendorNamespace = $parts[0] . '\\' . $parts[1];

        foreach ($this->bundles as $bundleName => $bundleClass) {
            if (!PaymentPluginBootstrap::isPaymentPlugin($bundleClass)) {
                continue;
            }

            // Check if bundle class namespace matches handler namespace
            if (str_starts_with($bundleClass, $vendorNamespace)) {
                return $bundleName . '.config';
            }
        }

        return null;
    }
}
