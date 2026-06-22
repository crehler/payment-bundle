<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Event;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

use function is_array;
use function json_decode;

/**
 * Event emitted when a payment notification is received.
 *
 * Payment providers should subscribe to this event, check if the request
 * matches their expected format (by headers, body structure, etc.),
 * and handle it if it does.
 */
final class PaymentNotificationReceivedEvent extends Event
{
    /**
     * @var string
     */
    public const EVENT_NAME = 'crehler.payment.notification.received';

    private bool $handled = false;
    private int $responseCode = 200;
    private ?string $responseMessage = null;

    public function __construct(
        public readonly Request $request,
        public readonly Context $context,
    ) {
    }

    /**
     * Mark the event as handled by a provider.
     * Once handled, other providers should skip processing.
     */
    public function setHandled(int $responseCode = 200, ?string $message = null): void
    {
        $this->handled = true;
        $this->responseCode = $responseCode;
        $this->responseMessage = $message;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    public function getResponseMessage(): ?string
    {
        return $this->responseMessage;
    }

    /**
     * Get request body as array (for JSON payloads).
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        $content = $this->request->getContent();
        if (empty($content)) {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get all headers as flat array (first value only).
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->request->headers->all() as $key => $values) {
            $headers[$key] = $values[0] ?? '';
        }

        return $headers;
    }
}
