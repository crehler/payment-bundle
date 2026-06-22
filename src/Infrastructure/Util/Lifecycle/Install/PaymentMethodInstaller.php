<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install;

use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\InstallInterface;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\PaymentMethodManager;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

readonly class PaymentMethodInstaller implements InstallInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function install(InstallContext $context, string $pluginId, string $baseClass): void
    {
        (new PaymentMethodManager($this->container))->create(
            context: $context->getContext(),
            pluginId: $pluginId,
            baseClass: $baseClass
        );
    }
}
