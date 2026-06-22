<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * DI Extension for CrehlerPaymentBundle.
 *
 * Note: Services are loaded automatically by Shopware\Core\Framework\Bundle::registerContainerFile()
 * from Resources/config/services.yaml - no manual loading needed here.
 */
final class CrehlerPaymentExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Services are auto-loaded by Shopware\Core\Framework\Bundle
        // This extension is kept for potential future configuration processing
    }
}
