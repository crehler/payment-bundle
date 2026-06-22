<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util;

use Crehler\PaymentBundle\Infrastructure\Enum\ExtensionsEnum;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use ReflectionClass;
use Throwable;

use function get_class;
use function is_string;
use function ltrim;
use function pathinfo;
use function rtrim;
use function str_ends_with;
use function str_replace;

final readonly class PluginHelper
{
    /**
     * @var string
     */
    public const PHP_EXTENSION = '.php';

    public function __construct(
        private EnhancedLogger $logger,
    ) {
    }

    public function getPluginDirectory(string|object $pluginClass): ?string
    {
        try {
            $class = new ReflectionClass($pluginClass);

            $path = $class->getFileName();
            $fileName = $class->getShortName();

            if (str_ends_with($path, ExtensionsEnum::PHP->value)) {
                return str_replace('/' . $fileName . self::PHP_EXTENSION, '', $path);
            }

            return $path;
        } catch (Throwable $e) {
            $this->logger->error('Failed to resolve plugin directory', [
                'exception' => $e,
                'pluginClass' => is_string($pluginClass) ? $pluginClass : get_class($pluginClass),
            ]);

            return null;
        }
    }

    public function getPluginNamespaceRoot(string|object $pluginClass): string
    {
        $class = new ReflectionClass($pluginClass);

        $ns = rtrim($class->getNamespaceName(), '\\');

        return $ns === '' ? '' : ($ns . '\\');
    }

    public function buildPathFromSrc(string|object $pluginClass, string $relativePathFromSrc): string
    {
        $base = rtrim($this->getPluginDirectory($pluginClass), '/');

        return $base . '/' . ltrim($relativePathFromSrc, '/');
    }

    public function buildNamespace(string|object $pluginClass, string $relativeNamespace): string
    {
        return $this->getPluginNamespaceRoot($pluginClass) . ltrim($relativeNamespace, '\\');
    }

    public static function filenameToClassBasename(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }
}
