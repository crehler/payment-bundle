<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity\Configuration;

use ReflectionClass;
use Throwable;

use function get_called_class;

final readonly class Configuration
{
    public static function factor(array $data): self
    {
        $reflection = new ReflectionClass(get_called_class());
        $instance = $reflection->newInstanceWithoutConstructor();
        foreach ($data as $key => $value) {
            $reflectionProperty = $reflection->getProperty($key);
            try {
                $reflectionProperty->setValue($instance, $value);
            } catch (Throwable $e) {
                // TODO LOG
                if ($reflectionProperty->getType()->allowsNull()) {
                    $reflectionProperty->setValue($instance, null);
                }
            }
        }

        return $instance;
    }
}
