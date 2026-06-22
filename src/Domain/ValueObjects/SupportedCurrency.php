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

final readonly class SupportedCurrency
{
    public function __construct(
        #[Assert\NotNull(message: 'ISO code cannot be null')]
        public string $isoCode,
        #[Assert\NotNull(message: 'ID cannot be null')]
        public string $id,
    ) {
        $this->validate();
    }

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
