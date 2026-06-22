<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Enum;

use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Activate\PaymentMethodActivator;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Deactivate\PaymentMethodDeactivator;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install\{BundleConfigDefaultsInstaller, BundleMigrationInstaller, CustomFieldCreator, PaymentMethodInstaller};
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Update\PaymentMethodUpdater;

enum LifecycleEnum: string
{
    case INSTALL = 'install';
    case UNINSTALL = 'uninstall';
    case UPDATE = 'update';
    case ACTIVATE = 'activate';
    case DEACTIVATE = 'deactivate';

    public function handle(): array
    {
        return match ($this) {
            self::INSTALL => [
                // Must run first: creates the bundle's own tables (e.g. stored cards)
                // before anything that may rely on them. Also runs on update (see
                // LifecycleManager::update, which iterates the INSTALL list).
                BundleMigrationInstaller::class,
                PaymentMethodInstaller::class,
                CustomFieldCreator::class,
                // Seeds shared bundle config defaults (display settings + transaction
                // description) into system_config. Runs on install and update.
                BundleConfigDefaultsInstaller::class,
            ],
            self::UNINSTALL, self::DEACTIVATE => [
                PaymentMethodDeactivator::class,
            ],
            self::UPDATE => [
                PaymentMethodUpdater::class,
            ],
            self::ACTIVATE => [
                PaymentMethodActivator::class,
            ],
        };
    }
}
