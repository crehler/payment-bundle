<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Update;

use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces\UpdateInterface;
use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\PaymentMethodManager;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class PaymentMethodUpdater implements UpdateInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function update(UpdateContext $updateContext, string $pluginId, string $baseClass): void
    {
        (new PaymentMethodManager($this->container))
            ->update(
                context: $updateContext->getContext(),
                pluginId: $pluginId,
                baseClass: $baseClass
            );
    }
}
