<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Shared;

use RuntimeException;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;

use function hash_equals;
use function hash_hmac;
use function strlen;
use function substr;

/**
 * HMAC-signs and verifies arbitrary string values (e.g. a gateway redirect URL
 * carried through the browser) so a value the server produced cannot be swapped
 * by the client. Used to close the open-redirect on the payment-transition page:
 * the handler signs the gateway target, the controller refuses any target whose
 * signature does not match.
 *
 * Uses a 128-bit truncated HMAC-SHA256 keyed on APP_SECRET; refuses to operate
 * with an empty secret rather than producing an attacker-derivable signature.
 */
final class UrlSigner
{
    private const SIGNATURE_LENGTH = 32;

    public function sign(string $value): string
    {
        return substr(hash_hmac('sha256', $value, $this->secret()), 0, self::SIGNATURE_LENGTH);
    }

    public function verify(string $value, string $signature): bool
    {
        if (strlen($signature) !== self::SIGNATURE_LENGTH) {
            return false;
        }

        return hash_equals($this->sign($value), $signature);
    }

    private function secret(): string
    {
        $secret = (string) EnvironmentHelper::getVariable('APP_SECRET', '');

        if ($secret === '') {
            throw new RuntimeException('APP_SECRET is not configured; cannot sign payment redirect target. Refusing to fall back to an empty HMAC key (would produce a deterministic, attacker-derivable signature).');
        }

        return $secret;
    }
}
