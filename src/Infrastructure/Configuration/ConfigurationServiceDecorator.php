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
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\Service\{AppConfigReader, ConfigurationService};
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\DependencyInjection\Attribute\{AsDecorator, Autowire, AutowireDecorated};
use Throwable;

use function array_key_exists;
use function array_unshift;
use function explode;

/**
 * Decorates ConfigurationService to inject shared payment bundle configuration
 * into all Crehler payment provider plugins (detected via the CREHLER_PAYMENT_PLUGIN marker).
 *
 * Adds "Display settings" card with:
 * - embedCardForm (bool) - embed card form in checkout
 * - blikInputPosition (select) - BLIK code input position
 */
#[AsDecorator(decorates: ConfigurationService::class)]
class ConfigurationServiceDecorator extends ConfigurationService
{
    private const BUNDLE_SETTINGS_CARD_NAME = 'crPaymentBundleSettings';
    private const INTEGRATION_TRAILER_CARD_NAME = 'crPaymentIntegrationTrailer';

    /** @var array<string, bool> */
    private array $paymentPluginCache = [];

    /**
     * @param array<string, string> $bundles
     */
    public function __construct(
        #[AutowireDecorated]
        private readonly ConfigurationService $inner,
        #[Autowire(param: 'kernel.bundles')]
        private readonly array $bundles,
        private readonly EnhancedLogger $logger,
        ConfigReader $configReader,
        AppConfigReader $appConfigReader,
        #[Autowire(service: 'app.repository')]
        EntityRepository $appRepository,
        SystemConfigService $systemConfigService,
        // Core's own logger — NOT our payment_bundle channel. The parent
        // ConfigurationService logs benign debug noise (e.g. "no config.xml" for
        // bundles without configuration); routing that through EnhancedLogger would
        // spam payment_bundle_*.log (its handler is level: debug).
        LoggerInterface $coreLogger,
    ) {
        $parentConstructor = (new ReflectionClass(ConfigurationService::class))->getConstructor();
        if ($parentConstructor !== null && $parentConstructor->getNumberOfParameters() >= 6) {
            parent::__construct($this->bundles, $configReader, $appConfigReader, $appRepository, $systemConfigService, $coreLogger);
        } else {
            parent::__construct($this->bundles, $configReader, $appConfigReader, $appRepository, $systemConfigService);
        }
    }

    public function getConfiguration(string $domain, Context $context): array
    {
        $config = $this->inner->getConfiguration($domain, $context);

        try {
            $pluginName = $this->extractPluginName($domain);

            if ($pluginName !== null && $this->isPaymentPlugin($pluginName)) {
                $config = $this->injectBundleConfiguration($config, $domain);
                $config = $this->prependIntegrationTrailer($config, $domain);
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to inject payment bundle configuration', [
                'exception' => $e->getMessage(),
                'domain' => $domain,
            ]);
        }

        return $config;
    }

    /**
     * Extract plugin name from configuration domain.
     */
    private function extractPluginName(string $domain): ?string
    {
        $parts = explode('.', $domain);

        return $parts[0] ?? null;
    }

    /**
     * Check if the given plugin is a Crehler payment plugin (CREHLER_PAYMENT_PLUGIN marker).
     */
    private function isPaymentPlugin(string $pluginName): bool
    {
        if (array_key_exists($pluginName, $this->paymentPluginCache)) {
            return $this->paymentPluginCache[$pluginName];
        }

        $result = isset($this->bundles[$pluginName])
            && PaymentPluginBootstrap::isPaymentPlugin($this->bundles[$pluginName]);

        $this->paymentPluginCache[$pluginName] = $result;

        return $result;
    }

    /**
     * Inject bundle configuration card into existing config.
     *
     * @param array<int, array<string, mixed>> $config
     *
     * @return array<int, array<string, mixed>>
     */
    private function injectBundleConfiguration(array $config, string $domain): array
    {
        // Check if our card already exists
        foreach ($config as $card) {
            if (isset($card['name']) && $card['name'] === self::BUNDLE_SETTINGS_CARD_NAME) {
                return $config;
            }
        }

        // Add our configuration card at the end
        $config[] = [
            'name' => self::BUNDLE_SETTINGS_CARD_NAME,
            'title' => BundleConfigField::getCardTitle(),
            'elements' => BundleConfigField::getAllElements($domain),
        ];

        return $config;
    }

    /**
     * Prepend the CREHLER "payment integration" trailer as the first card of every
     * payment plugin's config form. The single element is an "advanced custom input
     * field" — a component element (no value) that renders the cr-payment-integration-trailer
     * admin component. Idempotent: skips if already present.
     *
     * The element matches the post-reshape shape ConfigurationService emits
     * ({name, config:{...}}): componentName lives inside `config`, as the admin's
     * sw-system-config reads element.config.componentName.
     *
     * @param array<int, array<string, mixed>> $config
     *
     * @return array<int, array<string, mixed>>
     */
    private function prependIntegrationTrailer(array $config, string $domain): array
    {
        // Mirrors injectBundleConfiguration()'s idempotency pattern: a card named
        // self::INTEGRATION_TRAILER_CARD_NAME (cf. self::BUNDLE_SETTINGS_CARD_NAME) is
        // skipped if already present. Keep both methods' guard/shape conventions in sync.
        foreach ($config as $card) {
            if (isset($card['name']) && $card['name'] === self::INTEGRATION_TRAILER_CARD_NAME) {
                return $config;
            }
        }

        array_unshift($config, [
            'name' => self::INTEGRATION_TRAILER_CARD_NAME,
            'elements' => [
                [
                    'name' => $domain . '.' . self::INTEGRATION_TRAILER_CARD_NAME,
                    'config' => [
                        'componentName' => 'cr-payment-integration-trailer',
                    ],
                ],
            ],
        ]);

        return $config;
    }
}
