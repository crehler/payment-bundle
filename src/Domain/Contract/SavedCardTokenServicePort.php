<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Contract;

use Crehler\PaymentBundle\Domain\Entity\SavedCardCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SavedCardTokenServicePort
{
    public function getSavedCards(string $paymentMethodId, SalesChannelContext $context): SavedCardCollection;
}
