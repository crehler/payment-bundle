<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle;

use Crehler\PaymentBundle\Application\Port\Driven\{GatewayConnectionCheckerInterface, RefundProviderPort, RefundReasonProviderInterface};
use Crehler\PaymentBundle\DependencyInjection\CrehlerPaymentExtension;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * Shopware Bundle providing shared payment functionality for Polish payment providers.
 * This bundle should be registered via getAdditionalBundles() in payment plugins.
 *
 * Extends Shopware\Core\Framework\Bundle to enable:
 * - Automatic Twig template registration from Resources/views
 * - Route configuration
 * - Migration support
 */
class CrehlerPaymentBundle extends Bundle
{
    protected ExtensionInterface|false|null $extension = null;

    public function getPath(): string
    {
        return __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // RefundProviderPort implementations live in the provider plugins (Tpay/PayU/…).
        // Tag them globally so AbstractPaymentMethodHandler can collect them via
        // #[AutowireIterator(RefundProviderPort::class)] — without this the iterator is
        // empty and supports(REFUND) returns false ("unknown refund handler").
        $container->registerForAutoconfiguration(RefundProviderPort::class)
            ->addTag(RefundProviderPort::class);

        // Same pattern for the optional refund-reason port: provider plugins that expose
        // predefined refund reasons get collected by the admin endpoint via
        // #[AutowireIterator(RefundReasonProviderInterface::class)].
        $container->registerForAutoconfiguration(RefundReasonProviderInterface::class)
            ->addTag(RefundReasonProviderInterface::class);

        // Same pattern for the connection-checker port: provider plugins implement a
        // real credential probe, collected by the shared ConnectionTestController via
        // #[AutowireIterator(GatewayConnectionCheckerInterface::class)].
        $container->registerForAutoconfiguration(GatewayConnectionCheckerInterface::class)
            ->addTag(GatewayConnectionCheckerInterface::class);
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new CrehlerPaymentExtension();
        }

        return $this->extension;
    }
}
