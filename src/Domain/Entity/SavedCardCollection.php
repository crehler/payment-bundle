<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity;

use Crehler\PaymentBundle\Domain\ValueObjects\SavedCard;

use function array_filter;

final readonly class SavedCardCollection
{
    /**
     * @param array<SavedCard> $savedCards
     */
    public function __construct(
        public array $savedCards,
    ) {
    }

    public function addSavedCard(SavedCard $savedCard): self
    {
        if (array_filter($this->savedCards, fn (SavedCard $card) => $card->token === $savedCard->token)) {
            return $this;
        }

        return new self([...$this->savedCards, $savedCard]);
    }
}
