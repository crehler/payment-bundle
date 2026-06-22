<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\ValueObjects;

use Crehler\PaymentBundle\Domain\Exception\InvalidArgumentException;
use Symfony\Component\Validator\{Constraints as Assert, Validation};

use function count;
use function implode;
use function round;

final readonly class Money
{
    /**
     * @param int    $amount   Amount in the smallest currency unit (e.g., cents for EUR/USD)
     * @param string $currency ISO 4217 currency code
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        #[Assert\NotNull(message: 'Line item ID cannot be null')]
        public int $amount,

        #[Assert\NotNull(message: 'Currency cannot be null')]
        public string $currency,
    ) {
        $this->validate();
    }

    /**
     * Creates a new Money object with the specified amount.
     */
    public function withAmount(int $amount): self
    {
        return new self($amount, $this->currency);
    }

    /**
     * Creates a new Money object with the specified currency.
     */
    public function withCurrency(string $currency): self
    {
        return new self($this->amount, $currency);
    }

    /**
     * Adds the specified Money to this Money and returns a new Money object.
     *
     * @throws InvalidArgumentException If currencies don't match
     */
    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot add money with different currencies');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtracts the specified Money from this Money and returns a new Money object.
     *
     * @throws InvalidArgumentException If currencies don't match
     */
    public function subtract(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot subtract money with different currencies');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * Multiplies this Money by the specified factor and returns a new Money object.
     */
    public function multiply(float $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    /**
     * Checks if this Money is equal to the specified Money.
     */
    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    /**
     * Validates that the money value is in a valid state.
     *
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($this);

        if (count($violations) > 0) {
            $errorMessages = [];
            foreach ($violations as $violation) {
                $errorMessages[] = $violation->getMessage();
            }

            throw new InvalidArgumentException(implode(', ', $errorMessages));
        }
    }
}
