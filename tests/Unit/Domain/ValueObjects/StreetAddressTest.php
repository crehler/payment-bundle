<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Domain\ValueObjects;

use Crehler\PaymentBundle\Domain\ValueObjects\StreetAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function strlen;

/**
 * The split exists because Shopware stores one street line and the gateways want three
 * fields. Every case below is a shape that appears in real Polish address books; the one
 * that matters most is "3 Maja 12", which is why the number is read from the end.
 */
final class StreetAddressTest extends TestCase
{
    /**
     * @return array<string, array{string, string, ?string, ?string}>
     */
    public static function lines(): array
    {
        return [
            'street and house' => ['Kwiatowa 5', 'Kwiatowa', '5', null],
            'house and apartment with slash' => ['Kwiatowa 5/3', 'Kwiatowa', '5', '3'],
            'apartment spelled m.' => ['Kwiatowa 5 m. 3', 'Kwiatowa', '5', '3'],
            'apartment spelled lok.' => ['Kwiatowa 5 lok. 3', 'Kwiatowa', '5', '3'],
            'letter suffix on the house' => ['Aleje Jerozolimskie 123A', 'Aleje Jerozolimskie', '123A', null],
            'letters on both numbers' => ['Kwiatowa 12A/3B', 'Kwiatowa', '12A', '3B'],
            'multi-word street' => ['Plac Konstytucji 1/5', 'Plac Konstytucji', '1', '5'],

            // A street named after a date. Reading the number left to right would call the
            // house "3" and the street "Maja", and the parcel would go to another address.
            'street that begins with a number' => ['3 Maja 12', '3 Maja', '12', null],
            'street that begins with a number, with apartment' => ['1 Sierpnia 8/2', '1 Sierpnia', '8', '2'],

            // Nothing to split. Inventing a house number here would be worse than leaving it
            // out: the shop may legitimately hold the number in a second address line.
            'no number at all' => ['Kwiatowa', 'Kwiatowa', null, null],
            'empty line' => ['', '', null, null],

            'comma separator' => ['Kwiatowa, 5', 'Kwiatowa', '5', null],
            'surrounding whitespace' => ['  Kwiatowa 5  ', 'Kwiatowa', '5', null],
        ];
    }

    #[DataProvider('lines')]
    public function testTheLineSplitsIntoTheFieldsTheGatewayAsksFor(
        string $line,
        string $street,
        ?string $houseNumber,
        ?string $apartmentNumber,
    ): void {
        $address = StreetAddress::fromLine($line);

        self::assertSame($street, $address->street);
        self::assertSame($houseNumber, $address->houseNumber);
        self::assertSame($apartmentNumber, $address->apartmentNumber);
    }

    /**
     * PayNow caps street at 100 characters and rejects the whole payment over it. A customer
     * cannot see or fix that field at this point, so it is truncated rather than fatal.
     */
    public function testAnOverlongStreetIsTruncatedRatherThanFailingThePayment(): void
    {
        $address = StreetAddress::fromLine(str_repeat('a', 150) . ' 5');

        self::assertSame(100, strlen($address->street));
        self::assertSame('5', $address->houseNumber);
    }

    public function testZipCodesAreUpperCasedBecauseTheGatewayRejectsLowerCase(): void
    {
        // PayNow validates the postcode against ^[A-Z0-9-_ ]+$, so a lower-case foreign
        // code fails on a field the customer never sees.
        self::assertSame('EC1A 1BB', StreetAddress::normalizeZipCode(' ec1a 1bb '));
        self::assertSame('00-950', StreetAddress::normalizeZipCode('00-950'));
    }

    /**
     * An address with no postal code and one with a blank postal code are the same fact,
     * and both have to survive as null: the payload drops null fields, while an empty
     * string would be sent and rejected by the very pattern above.
     */
    public function testAMissingPostalCodeStaysMissingRatherThanBecomingEmpty(): void
    {
        self::assertNull(StreetAddress::normalizeZipCode(null));
        self::assertNull(StreetAddress::normalizeZipCode(''));
        self::assertNull(StreetAddress::normalizeZipCode('   '));
    }
}
