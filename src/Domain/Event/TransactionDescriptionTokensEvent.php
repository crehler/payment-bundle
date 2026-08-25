<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Event;

use Crehler\PaymentBundle\Domain\Entity\Order\Order;
use Symfony\Contracts\EventDispatcher\Event;

use function str_contains;

/**
 * Extension point for the configurable transaction description.
 *
 * The bundle only knows about a single order, so tokens describing anything wider — an order
 * group produced by split deliveries, for example — have to come from the plugin that owns that
 * concept. Listeners receive the already-resolved default tokens and may overwrite them.
 *
 * Listeners should guard their work with usesToken(): resolving a token the configured template
 * never mentions is wasted I/O on every single payment initiation.
 */
final class TransactionDescriptionTokensEvent extends Event
{
    /**
     * @var string
     */
    public const EVENT_NAME = 'crehler.payment.transaction_description.tokens';

    /**
     * @param array<string, string> $tokens token (with braces) => replacement value
     */
    public function __construct(
        public readonly Order $order,
        public readonly string $configDomain,
        public readonly string $template,
        private array $tokens,
    ) {
    }

    /**
     * Whether the configured template actually contains the given token, e.g. "{{ orderIds }}".
     */
    public function usesToken(string $token): bool
    {
        return str_contains($this->template, $token);
    }

    public function setToken(string $token, string $value): void
    {
        $this->tokens[$token] = $value;
    }

    /**
     * @return array<string, string>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }
}
