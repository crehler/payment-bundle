<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install;

use Crehler\PaymentBundle\CrehlerPaymentBundle;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\{InstallInterface, UpdateInterface};
use Shopware\Core\Framework\Migration\{MigrationCollectionLoader, MigrationSource};
use Shopware\Core\Framework\Plugin\Context\{InstallContext, UpdateContext};
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs CrehlerPaymentBundle's own DB migrations whenever a provider plugin
 * (Tpay / PayU / PayNow …) is installed or updated.
 *
 * CrehlerPaymentBundle is a Shopware Bundle, not a plugin, so Shopware never
 * auto-runs its migrations (only plugin migrations run on plugin install). Without
 * this the bundle tables — e.g. crehler_payment_stored_card — are never created and
 * storefront flows that read them (saved cards on /checkout/confirm) crash with a
 * "table doesn't exist" error.
 *
 * The migration source name equals the bundle short name, matching how
 * Shopware\Core\Framework\Bundle::registerMigrationPath() registers it.
 */
final class BundleMigrationInstaller implements InstallInterface, UpdateInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function install(InstallContext $context, string $pluginId, string $baseClass): void
    {
        $this->runMigrations();
    }

    public function update(UpdateContext $context, string $pluginId, string $baseClass): void
    {
        $this->runMigrations();
    }

    private function runMigrations(): void
    {
        /** @var MigrationCollectionLoader $loader */
        $loader = $this->container->get(MigrationCollectionLoader::class);

        // Register the bundle's migration source at runtime. During a provider plugin
        // install the bundle isn't an active kernel bundle yet, so its source isn't
        // registered — mirror Shopware\Core\Framework\Plugin\PluginLifecycleService,
        // which addSource()s before collect(). addSource() is idempotent (keyed by name).
        $bundle = new CrehlerPaymentBundle();
        $loader->addSource(new MigrationSource($bundle->getName(), [
            $bundle->getPath() . '/Migration' => $bundle->getMigrationNamespace(),
        ]));

        $migrations = $loader->collect($bundle->getName());
        $migrations->sync();
        $migrations->migrateInPlace();
    }
}
