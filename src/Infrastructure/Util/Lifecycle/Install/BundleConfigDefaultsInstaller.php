<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install;

use Crehler\PaymentBundle\Infrastructure\Configuration\BundleConfigField;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\{InstallInterface, UpdateInterface};
use Shopware\Core\Framework\Plugin\Context\{InstallContext, UpdateContext};
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function end;
use function explode;

/**
 * Persists the shared bundle config defaults (display settings + transaction
 * description) into system_config when a provider plugin is installed or updated.
 *
 * The admin elements declare defaultValue, but Shopware only writes that to the DB
 * on first save — until then reads return null. Seeding here makes the values real
 * in the database from install time. Existing values are never overwritten (guarded
 * by a null check), so it is safe to re-run on update.
 */
final class BundleConfigDefaultsInstaller implements InstallInterface, UpdateInterface
{
    private readonly SystemConfigService $systemConfigService;

    public function __construct(
        ContainerInterface $container,
    ) {
        $this->systemConfigService = $container->get(SystemConfigService::class);
    }

    public function install(InstallContext $context, string $pluginId, string $baseClass): void
    {
        $this->seedDefaults($baseClass);
    }

    public function update(UpdateContext $context, string $pluginId, string $baseClass): void
    {
        $this->seedDefaults($baseClass);
    }

    private function seedDefaults(string $baseClass): void
    {
        $domain = $this->resolveConfigDomain($baseClass);

        foreach (BundleConfigField::cases() as $field) {
            $key = $domain . '.' . $field->value;

            // Don't clobber values an operator (or a previous install) already set.
            if ($this->systemConfigService->get($key) !== null) {
                continue;
            }

            $this->systemConfigService->set($key, $field->defaultValue());
        }
    }

    /**
     * Config domain = plugin class short name + ".config"
     * (e.g. Crehler\Tpay\CrehlerTpay -> CrehlerTpay.config), matching how
     * ConfigurationServiceDecorator / PaymentBundleConfigService resolve it.
     */
    private function resolveConfigDomain(string $baseClass): string
    {
        $parts = explode('\\', $baseClass);

        return end($parts) . '.config';
    }
}
