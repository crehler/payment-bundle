<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces;

use Shopware\Core\Framework\Plugin\Context\InstallContext;

interface InstallInterface
{
    /**
     * @param string|null $baseClass Class from where plugin called bundle to install
     */
    public function install(InstallContext $context, string $pluginId, string $baseClass): void;
}
