<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle;

use Crehler\PaymentBundle\Infrastructure\Enum\LifecycleEnum;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\{ActivateInterface, DeactivateInterface, InstallInterface, UpdateInterface};
use Shopware\Core\Framework\Plugin\Context\{ActivateContext, DeactivateContext, InstallContext, UninstallContext, UpdateContext};
use Symfony\Component\DependencyInjection\ContainerInterface;

use function class_exists;
use function class_implements;
use function in_array;

final class LifecycleManager
{
    public static function install(
        InstallContext $context,
        ContainerInterface $container,
        string $pluginId,
        string $baseClass,
    ): void {
        foreach (LifecycleEnum::INSTALL->handle() as $handler) {
            if (class_exists($handler) && self::classImplementsInterface(className: $handler, interfaceName: InstallInterface::class)) {
                (new $handler($container))->install(
                    context: $context,
                    pluginId: $pluginId,
                    baseClass: $baseClass
                );
            }
        }
    }

    public static function uninstall(
        UninstallContext $uninstallContext,
        ContainerInterface $container,
        string $pluginId,
        string $baseClass,
    ): void {
        foreach (LifecycleEnum::DEACTIVATE->handle() as $handler) {
            if (class_exists($handler) && self::classImplementsInterface(className: $handler, interfaceName: DeactivateInterface::class)) {
                (new $handler($container))->deactivate(
                    context: $uninstallContext,
                    pluginId: $pluginId,
                    baseClass: $baseClass
                );
            }
        }
    }

    public static function update(
        UpdateContext $context,
        ContainerInterface $container,
        string $pluginId,
        string $baseClass,
    ): void {
        foreach (LifecycleEnum::INSTALL->handle() as $handler) {
            if (class_exists($handler) && self::classImplementsInterface(className: $handler, interfaceName: UpdateInterface::class)) {
                (new $handler($container))->update(
                    context: $context,
                    pluginId: $pluginId,
                    baseClass: $baseClass
                );
            }
        }
    }

    public static function activate(
        ActivateContext $activateContext,
        ContainerInterface $container,
        string $pluginId,
        string $baseClass,
    ): void {
        foreach (LifecycleEnum::ACTIVATE->handle() as $handler) {
            if (class_exists($handler) && self::classImplementsInterface(className: $handler, interfaceName: ActivateInterface::class)) {
                (new $handler($container))->activate(
                    activateContext: $activateContext,
                    pluginId: $pluginId,
                    baseClass: $baseClass
                );
            }
        }
    }

    public static function deactivate(
        DeactivateContext|UninstallContext $deactivateContext,
        ContainerInterface $container,
        string $pluginId,
        string $baseClass,
    ): void {
        foreach (LifecycleEnum::DEACTIVATE->handle() as $handler) {
            if (class_exists($handler) && self::classImplementsInterface(className: $handler, interfaceName: DeactivateInterface::class)) {
                (new $handler($container))->deactivate(
                    context: $deactivateContext,
                    pluginId: $pluginId,
                    baseClass: $baseClass
                );
            }
        }
    }

    /**
     * Check if class implements interface.
     */
    private static function classImplementsInterface(string $className, string $interfaceName): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $interfacesArr = class_implements($className);

        return in_array($interfaceName, $interfacesArr);
    }
}
