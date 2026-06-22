<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Activate;

use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\ActivateInterface;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\PaymentMethodManager;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class PaymentMethodActivator implements ActivateInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function activate(ActivateContext $activateContext, string $pluginId, string $baseClass): void
    {
        (new PaymentMethodManager($this->container))
            ->activate(
                context: $activateContext->getContext(),
                pluginId: $pluginId,
                baseClass: $baseClass
            );
    }
}
