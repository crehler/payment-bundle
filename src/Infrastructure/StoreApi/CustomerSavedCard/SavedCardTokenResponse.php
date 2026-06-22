<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard;

use Crehler\PaymentBundle\Infrastructure\Struct\Card\SavedCardCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class SavedCardTokenResponse extends StoreApiResponse
{
    public const API_ALIAS = 'cr_payment_saved_card_token_response';

    public function __construct(SavedCardCollection $object)
    {
        parent::__construct($object);
    }

    public function getApiAlias(): string
    {
        return self::API_ALIAS;
    }

    public function get(): SavedCardCollection
    {
        return $this->object;
    }
}
