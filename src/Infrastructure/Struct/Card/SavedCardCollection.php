<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Struct\Card;

use Crehler\PaymentBundle\Domain\ValueObjects\SavedCard;
use Shopware\Core\Framework\Struct\Collection;

class SavedCardCollection extends Collection
{
    public function add($element): void
    {
        if (!$element instanceof SavedCard) {
            return;
        }

        parent::add($element);
    }

    public function getApiAlias(): string
    {
        return 'cr_payment_saved_card_collection';
    }
}
