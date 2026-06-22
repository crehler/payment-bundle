<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Port;

use Crehler\PaymentBundle\Domain\Contract\PaymentSubMethodPort;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface PaymentSubMethodProvider extends PaymentSubMethodPort
{
}
