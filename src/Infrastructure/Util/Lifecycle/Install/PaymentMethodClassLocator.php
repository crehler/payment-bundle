<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install;

use Crehler\PaymentBundle\Infrastructure\Enum\{ExtensionsEnum, PaymentDirectoriesPathEnum};
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\PaymentMethodLocatorInterface;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\ShopwarePaymentMethod;
use Crehler\PaymentBundle\Infrastructure\Util\PluginHelper;
use DirectoryIterator;

use function class_exists;
use function is_a;
use function is_dir;
use function ltrim;
use function str_ends_with;
use function Symfony\Component\String\u;

final readonly class PaymentMethodClassLocator implements PaymentMethodLocatorInterface
{
    public function __construct(
        private PluginHelper $pluginHelper,
    ) {
    }

    /**
     * Retrive PaymentMethod classes from Util/Install/PaymentMethodName
     */
    public function getMethods(string $baseClass, PaymentDirectoriesPathEnum $pathToFolder): array
    {
        $methods = [];
        $directoryPath = $this->pluginHelper->getPluginDirectory(pluginClass: $baseClass) . $pathToFolder->value;

        if (!is_dir($directoryPath)) {
            return [];
        }

        $namespacePrefix = $this->pluginHelper->getPluginNamespaceRoot(pluginClass: $baseClass);
        $namespacePrefix = u($namespacePrefix)
            ->append(ltrim($pathToFolder->namespace(), '\\'))
            ->toString();

        foreach (new DirectoryIterator($directoryPath) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            if (!str_ends_with($filename, ExtensionsEnum::PHP->value)) {
                continue;
            }

            $className = u($namespacePrefix)
                ->append(PluginHelper::filenameToClassBasename($filename))
                ->toString();

            if (!class_exists($className)) {
                continue;
            }

            if (!is_a($className, ShopwarePaymentMethod::class, true)) {
                continue;
            }

            $methods[] = new $className();
        }

        return $methods;
    }
}
