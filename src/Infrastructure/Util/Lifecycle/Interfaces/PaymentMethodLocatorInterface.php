<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces;

use Crehler\PaymentBundle\Infrastructure\Enum\PaymentDirectoriesPathEnum;

interface PaymentMethodLocatorInterface
{
    /**
     * @param string                     $baseClass    path to plugin src folder
     * @param PaymentDirectoriesPathEnum $pathToFolder path to folder inside plugin/src you want to scan
     */
    public function getMethods(string $baseClass, PaymentDirectoriesPathEnum $pathToFolder): array;
}
