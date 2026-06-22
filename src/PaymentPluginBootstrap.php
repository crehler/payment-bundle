<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle;

use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\LifecycleManager;
use ReflectionClass;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Context\{ActivateContext, DeactivateContext, InstallContext, UninstallContext, UpdateContext};
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function class_exists;
use function is_dir;

/**
 * Static bootstrap helper for Crehler payment provider plugins.
 *
 * Replaces the former AbstractPaymentPlugin base class. A consumer plugin MUST extend
 * Shopware\Core\Framework\Plugin and MUST NOT reference any class from this bundle in its
 * class signature (extends / implements / use-trait / typed property / class constant /
 * attribute). That keeps the consumer instantiable BEFORE crehler/payment-bundle has been
 * composer-installed — which is required, because Shopware instantiates the plugin and
 * reads Plugin::executeComposerCommands() (returning true triggers `composer require`)
 * before the bundle exists in vendor/. See PluginLifecycleService::installPlugin():
 * the composer require runs before the plugin's install() lifecycle.
 *
 * Consumers delegate to these static methods from inside method bodies only — those run
 * after the bundle has been pulled into vendor/ (lazy autoload), so it is safe to touch
 * bundle classes there.
 *
 * Detection: a consumer declares `public const CREHLER_PAYMENT_PLUGIN = true;`. The bundle
 * recognises payment plugins via self::isPaymentPlugin() — a scalar marker instead of a
 * shared base type, again to avoid the pre-install autoload problem.
 */
final class PaymentPluginBootstrap
{
    /**
     * Name of the scalar marker constant a consumer plugin must declare.
     */
    public const PLUGIN_MARKER = 'CREHLER_PAYMENT_PLUGIN';

    /**
     * Register CrehlerPaymentBundle as additional Symfony bundle.
     *
     * @return array<int, BundleInterface>
     */
    public static function additionalBundles(): array
    {
        return [new CrehlerPaymentBundle()];
    }

    /**
     * Import CrehlerPaymentBundle routes for an active consumer plugin.
     */
    public static function configureRoutes(RoutingConfigurator $routes, string $environment, bool $isActive): void
    {
        if (!$isActive) {
            return;
        }

        $bundleConfDir = __DIR__ . '/Resources/config';

        if (is_dir($bundleConfDir)) {
            $routes->import($bundleConfDir . '/{routes}/*' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($bundleConfDir . '/{routes}/' . $environment . '/**/*' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($bundleConfDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($bundleConfDir . '/{routes}_' . $environment . Kernel::CONFIG_EXTS, 'glob');
        }
    }

    public static function install(InstallContext $context, ContainerInterface $container, string $baseClass): void
    {
        LifecycleManager::install(
            context: $context,
            container: $container,
            pluginId: self::pluginId($container, $baseClass, $context->getContext()),
            baseClass: $baseClass,
        );
    }

    public static function update(UpdateContext $context, ContainerInterface $container, string $baseClass): void
    {
        LifecycleManager::update(
            context: $context,
            container: $container,
            pluginId: self::pluginId($container, $baseClass, $context->getContext()),
            baseClass: $baseClass,
        );
    }

    public static function activate(ActivateContext $context, ContainerInterface $container, string $baseClass): void
    {
        LifecycleManager::activate(
            activateContext: $context,
            container: $container,
            pluginId: self::pluginId($container, $baseClass, $context->getContext()),
            baseClass: $baseClass,
        );
    }

    public static function deactivate(
        DeactivateContext|UninstallContext $context,
        ContainerInterface $container,
        string $baseClass,
    ): void {
        LifecycleManager::deactivate(
            deactivateContext: $context,
            container: $container,
            pluginId: self::pluginId($container, $baseClass, $context->getContext()),
            baseClass: $baseClass,
        );
    }

    public static function uninstall(UninstallContext $context, ContainerInterface $container, string $baseClass): void
    {
        if ($context->keepUserData()) {
            return;
        }

        self::deactivate($context, $container, $baseClass);
    }

    /**
     * Runtime detection of a Crehler payment consumer plugin via the scalar marker
     * constant. Deliberately avoids any shared base type so it never triggers the
     * pre-install autoload problem described in the class docblock.
     */
    public static function isPaymentPlugin(string $class): bool
    {
        return class_exists($class) && (new ReflectionClass($class))->getConstant(self::PLUGIN_MARKER) === true;
    }

    private static function pluginId(ContainerInterface $container, string $baseClass, Context $context): string
    {
        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $container->get(PluginIdProvider::class);

        return $pluginIdProvider->getPluginIdByBaseClass($baseClass, $context);
    }
}
