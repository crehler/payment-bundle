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

final readonly class BlikPayment
{
    public function __construct(
        #[Assert\NotNull(message: 'Payment method ID cannot be null')]
        public string $paymentMethodId,
        #[Assert\NotNull(message: 'BLIK code cannot be null')]
        #[Assert\Regex(pattern: '/^\d{6}$/')]
        public string $blikCode,
    ) {
        $this->validate();
    }

    public function validate(): void
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
