<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Service;

use Brick\PhoneNumber\{PhoneNumber, PhoneNumberException, PhoneNumberFormat};
use Crehler\PaymentBundle\Shared\EnhancedLogger;

final readonly class PhoneNumberBuilder
{
    public function __construct(
        private EnhancedLogger $logger,
    ) {
    }

    public function buildPhoneNumber(string $phoneNumber, string $countryCode): string
    {
        try {
            if (empty($phoneNumber)) {
                return $this->buildFakePhoneNumber($countryCode);
            }

            $formattedPhoneNumber = PhoneNumber::parse($phoneNumber, $countryCode);

            return $formattedPhoneNumber->format(PhoneNumberFormat::INTERNATIONAL);
        } catch (PhoneNumberException $e) {
            $this->logger->error("Problem with parsing phone number: {$e->getMessage()}", ['exception' => $e]);

            return $this->buildFakePhoneNumber($countryCode);
        }
    }

    /**
     * Some payment gateways require a phone number to finalize transaction but in shopware phone number field is optional
     * so in case of missing phone number we need to build fake one to perform transaction.
     *
     * @param string $countryCode Country ISO code
     *
     * @throws PhoneNumberException
     */
    private function buildFakePhoneNumber(string $countryCode): string
    {
        $formattedPhoneNumber = PhoneNumber::getExampleNumber($countryCode);

        return $formattedPhoneNumber->format(PhoneNumberFormat::INTERNATIONAL);
    }
}
