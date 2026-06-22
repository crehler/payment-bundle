<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Deactivate;

use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\DeactivateInterface;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\PaymentMethodManager;
use Shopware\Core\Framework\Plugin\Context\{DeactivateContext, UninstallContext};
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class PaymentMethodDeactivator implements DeactivateInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function deactivate(DeactivateContext|UninstallContext $context, string $pluginId, string $baseClass): void
    {
        (new PaymentMethodManager(container: $this->container))
            ->deactivate(
                context: $context->getContext(),
                pluginId: $pluginId,
                baseClass: $baseClass
            );
    }
}
