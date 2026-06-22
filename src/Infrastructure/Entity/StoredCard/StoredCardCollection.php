<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Entity\StoredCard;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(StoredCardEntity $entity)
 * @method void                  set(string $key, StoredCardEntity $entity)
 * @method StoredCardEntity[]    getIterator()
 * @method StoredCardEntity[]    getElements()
 * @method StoredCardEntity|null get(string $key)
 * @method StoredCardEntity|null first()
 * @method StoredCardEntity|null last()
 */
class StoredCardCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StoredCardEntity::class;
    }
}
