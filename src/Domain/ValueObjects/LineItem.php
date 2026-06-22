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

/**
 * Represents a line item in an order
 * This is a value object - immutable and without identity.
 */
final readonly class LineItem
{
    /**
     * @param array<string, mixed> $customFields
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Line item ID cannot be empty')]
        public string $id,

        #[Assert\NotBlank(message: 'Product ID cannot be empty')]
        public string $productId,

        #[Assert\NotBlank(message: 'Product number cannot be empty')]
        public string $productNumber,

        #[Assert\NotBlank(message: 'Line item label cannot be empty')]
        public string $label,

        #[Assert\Positive(message: 'Quantity must be greater than zero')]
        public int $quantity,

        #[Assert\NotNull(message: 'Unit price cannot be null')]
        public Money $unitPrice,

        #[Assert\NotNull(message: 'Total price cannot be null')]
        public Money $totalPrice,

        #[Assert\GreaterThanOrEqual(value: 0, message: 'Tax rate cannot be negative')]
        public float $taxRate,

        public string $type = '',
        public ?string $manufacturerName = null,

        public ?string $manufacturerNumber = null,

        #[Assert\Type(type: 'array', message: 'Custom fields must be an array')]
        public array $customFields = [],
    ) {
        $this->validate();
    }

    /**
     * Validates that the line item is in a valid state.
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
