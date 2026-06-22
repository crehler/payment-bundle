<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle\Interfaces;

use Shopware\Core\Framework\Plugin\Context\UpdateContext;

interface UpdateInterface
{
    public function update(UpdateContext $context, string $pluginId, string $baseClass): void;
}
