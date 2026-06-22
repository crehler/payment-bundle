<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle;

use Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Install\PaymentMethodClassLocator;
use Crehler\PaymentBundle\Infrastructure\Util\PluginHelper;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trait providing PluginHelper and PaymentMethodClassLocator initialization
 * with graceful fallback when EnhancedLogger service is not available.
 *
 * Use this trait in lifecycle classes (Install, Update, Activate, Deactivate)
 * that need access to PluginHelper but may run before the full service container is available.
 */
trait PluginHelperAwareTrait
{
    protected ?PluginHelper $pluginHelper = null;
    protected ?PaymentMethodClassLocator $paymentMethodClassLocator = null;

    protected function getPluginHelper(): PluginHelper
    {
        if ($this->pluginHelper === null) {
            $this->pluginHelper = new PluginHelper($this->getEnhancedLogger());
        }

        return $this->pluginHelper;
    }

    protected function getPaymentMethodClassLocator(): PaymentMethodClassLocator
    {
        if ($this->paymentMethodClassLocator === null) {
            $this->paymentMethodClassLocator = new PaymentMethodClassLocator($this->getPluginHelper());
        }

        return $this->paymentMethodClassLocator;
    }

    protected function getEnhancedLogger(): EnhancedLogger
    {
        $container = $this->getContainer();

        // Try to get EnhancedLogger from container if available (when bundle is fully loaded)
        // Fall back to NullLogger during initial plugin installation when services may not be available
        if ($container->has(EnhancedLogger::class)) {
            return $container->get(EnhancedLogger::class);
        }

        return new EnhancedLogger(new NullLogger());
    }

    abstract protected function getContainer(): ContainerInterface;
}
