<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\Connection;

/**
 * Outcome of a gateway credential check. Returned by GatewayConnectionCheckerInterface
 * implementations and surfaced by the shared admin "test connection" component.
 */
final readonly class ConnectionCheckResult
{
    public function __construct(
        public bool $success,
        public string $message,
    ) {
    }

    /**
     * Empty message = let the admin render its own translated confirmation
     * (cr-payment-config.test.success). Pass text only when the provider has something
     * to add that the generic snippet cannot say, e.g. the number of channels returned —
     * and then pass it translated, not hardcoded English.
     */
    public static function ok(string $message = ''): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
