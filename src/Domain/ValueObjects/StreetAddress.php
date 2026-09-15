<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\ValueObjects;

use function mb_strtoupper;
use function mb_substr;
use function preg_match;
use function trim;

/**
 * A Shopware street line split into the parts Polish gateways ask for separately.
 *
 * Shopware keeps the street as ONE field — "Kwiatowa 5/3" — while PayNow, and every other
 * gateway that has to hand an address to a courier or a BNPL underwriter, wants `street`,
 * `houseNumber` and `apartmentNumber` apart. There is no second field to read them from, so
 * the line has to be parsed, and parsing is the reason this is a value object with tests
 * rather than three lines inlined in one provider: the next gateway will need the same split.
 *
 * The number is taken from the END of the line, never the start. "3 Maja 12" is a street
 * named after a date, and a left-to-right reading makes the house number 3 and the street
 * "Maja". Anchoring at the end costs nothing and gets that whole family right.
 *
 * When the line carries no number at all the street survives whole and the number is null.
 * That is the honest outcome: a shop can legitimately hold "Kwiatowa" with the number in
 * the second address line, and inventing a "1" would put a customer's parcel elsewhere.
 */
final readonly class StreetAddress
{
    /**
     * PayNow's documented ceilings (street 1-100, houseNumber and apartmentNumber 16 each).
     * Other gateways are looser, so clamping here is safe for all of them, and a value that
     * is one character over the limit is worth truncating rather than failing the payment.
     */
    private const STREET_MAX_LENGTH = 100;
    private const NUMBER_MAX_LENGTH = 16;

    private function __construct(
        public string $street,
        public ?string $houseNumber = null,
        public ?string $apartmentNumber = null,
    ) {
    }

    public static function fromLine(string $line): self
    {
        $line = trim($line);

        // Anchored at the end: everything up to the last number-looking token is the street.
        // The separator between house and apartment is "/", "m." or "lok." — the three that
        // actually appear in Polish addresses.
        $pattern = '~^(?<street>.*?)[\s,]+(?<house>\d+\s?[a-zA-Z]?)'
            . '(?:\s*(?:/|m\.?|lok\.?)\s*(?<apartment>\d+\s?[a-zA-Z]?))?$~u';

        if (preg_match($pattern, $line, $matches) !== 1) {
            return new self(self::clamp($line, self::STREET_MAX_LENGTH));
        }

        return new self(
            self::clamp($matches['street'], self::STREET_MAX_LENGTH),
            self::clamp($matches['house'], self::NUMBER_MAX_LENGTH),
            isset($matches['apartment']) && $matches['apartment'] !== ''
                ? self::clamp($matches['apartment'], self::NUMBER_MAX_LENGTH)
                : null,
        );
    }

    /**
     * Postal codes are compared and printed upper-case by every carrier, and PayNow rejects
     * anything outside ^[A-Z0-9-_ ]+$ — so a lower-case foreign code would fail validation
     * on a field the customer cannot see or correct.
     */
    public static function normalizeZipCode(?string $zipCode): ?string
    {
        if ($zipCode === null) {
            return null;
        }

        $normalized = mb_strtoupper(trim($zipCode));

        // An address whose postal code is blank is the same fact as one that has none.
        // Returning "" here would push an empty string into a payload where the gateway
        // validates the field against a non-empty pattern; null gets dropped instead.
        return $normalized === '' ? null : $normalized;
    }

    private static function clamp(string $value, int $max): string
    {
        return mb_substr(trim($value), 0, $max);
    }
}
