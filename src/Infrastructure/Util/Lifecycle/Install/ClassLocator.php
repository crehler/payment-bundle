<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

use function sprintf;
use function str_ends_with;
use function str_replace;
use function Symfony\Component\String\u;

final readonly class ClassLocator
{
    /**
     * Get directory path of installed plugin.
     * Example: $baseClass = Crehler\Payu\CrehlerPayUPayment\
     * Result: Crehler\PayU
     *
     * @throws ReflectionException
     */
    public function getPluginNamespace(string $baseClass): string
    {
        $class = new ReflectionClass($baseClass);

        if (!str_ends_with($baseClass, $class->getShortName())) {
            return $baseClass;
        }

        return str_replace($class->getShortName(), '', $baseClass);
    }

    public function setCustomNamespaceFromPlugin(string $baseClass, string $customNamespace): string
    {
        $namespace = $this->getPluginNamespace(baseClass: $baseClass);

        return $namespace . $customNamespace;
    }

    /**
     * Get class instance by constructing the namespace dynamically.
     *
     * @template T
     *
     * @param string      $classNamespace Base namespace to use
     * @param string      $className      Name of the class to instantiate
     * @param string|null $interfaceClass Optional interface class for type hinting
     *
     * @return T Instance of the requested class
     */
    public function getClass(string $classNamespace, string $className, ?string $interfaceClass): object
    {
        $fullClassName = u($classNamespace)
            ->append($className)
            ->toString();

        $instance = new $fullClassName();

        if ($interfaceClass !== null && !($instance instanceof $interfaceClass)) {
            throw new InvalidArgumentException(sprintf('Class "%s" must implement interface "%s"', $fullClassName, $interfaceClass));
        }

        return $instance;
    }
}
