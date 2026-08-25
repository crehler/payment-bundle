<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Domain\Entity\Order\Order;
use Crehler\PaymentBundle\Domain\Event\TransactionDescriptionTokensEvent;
use Crehler\PaymentBundle\Infrastructure\Configuration\BundleConfigField;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function array_keys;
use function array_values;
use function mb_strcut;
use function preg_replace;
use function str_contains;
use function str_replace;
use function strlen;
use function trim;

/**
 * Renders the shared, configurable transaction description from a token template.
 *
 * Single source of truth for what every provider sends to its gateway as the
 * human-readable description. Each plugin's payload/DTO factory calls render() with
 * its own config domain; the gateway-unique id stays the transaction UUID (not
 * configurable). Lightweight token replacement — intentionally NOT Twig (no SSTI
 * surface, no template engine dependency for a few placeholders).
 */
final readonly class TransactionDescriptionRenderer
{
    private const DEFAULT_TEMPLATE = '{{ orderNumber }}';
    private const TOKEN_SALES_CHANNEL_NAME = '{{ salesChannelName }}';

    /**
     * Separator run that must never start or end a rendered description: leftovers from an empty
     * token or from a hard cut. Expressed as a regex class, not as a trim() charlist — trim() works
     * on bytes, and this set contains multibyte characters, so it would happily slice a UTF-8
     * sequence in half (trim("Opis ✓") loses the last byte of the check mark).
     *
     * @var string
     */
    private const SEPARATOR_RUN = '[\s\x{0000}\x{000B}\x{002D}\x{2013}\x{2014}\x{007C}\x{00B7}\x{002C}]+';

    public function __construct(
        private SystemConfigService $systemConfigService,
        private EntityRepository $salesChannelRepository,
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.payment_bundle')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string   $configDomain   plugin config domain, e.g. "CrehlerTpay.config"
     * @param int|null $maxLengthBytes gateway limit for the description field, in BYTES — gateways
     *                                 validate with strlen(), not with a character count. Null means
     *                                 the caller imposes no limit.
     */
    public function render(
        string $configDomain,
        Order $order,
        ?string $salesChannelId = null,
        ?int $maxLengthBytes = null,
    ): string {
        $template = trim($this->systemConfigService->getString(
            $configDomain . '.' . BundleConfigField::TRANSACTION_DESCRIPTION->value,
            $salesChannelId,
        ));

        if ($template === '') {
            $template = self::DEFAULT_TEMPLATE;
        }

        $replacements = [
            '{{ orderNumber }}' => $order->orderNumber,
            '{{ customerName }}' => trim($order->customer->firstName . ' ' . $order->customer->lastName),
            // The order's _uniqueIdentifier, i.e. what an integration reading the DAL keys orders
            // by. "orderIds" defaults to that very same single id, so a template using it stays
            // valid when nothing extends it — see the event below.
            '{{ orderId }}' => $order->id,
            '{{ orderIds }}' => $order->id,
        ];

        // Resolve the sales channel name only when the template actually uses it.
        if (str_contains($template, self::TOKEN_SALES_CHANNEL_NAME)) {
            $replacements[self::TOKEN_SALES_CHANNEL_NAME] = $this->resolveSalesChannelName($order->salesChannelId);
        }

        $event = new TransactionDescriptionTokensEvent($order, $configDomain, $template, $replacements);
        $this->eventDispatcher->dispatch($event, TransactionDescriptionTokensEvent::EVENT_NAME);
        $replacements = $event->getTokens();

        $rendered = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Collapse whitespace left by empty tokens (e.g. missing sales channel name)
        // and trim stray separators so the gateway never receives "10001 - ".
        $rendered = (string) preg_replace('/\s{2,}/', ' ', $rendered);
        $rendered = $this->trimSeparators($rendered);

        return $this->enforceByteLimit($rendered, $maxLengthBytes, $configDomain, $salesChannelId);
    }

    /**
     * Last-resort guard: an over-long description makes the gateway reject the transaction, which
     * would take down every payment on the sales channel. Cutting is lossy — a halved UUID looks
     * valid but maps to nothing — so the cut is accompanied by a warning; the real fix is a shorter
     * template.
     */
    private function enforceByteLimit(
        string $rendered,
        ?int $maxLengthBytes,
        string $configDomain,
        ?string $salesChannelId,
    ): string {
        if ($maxLengthBytes === null || strlen($rendered) <= $maxLengthBytes) {
            return $rendered;
        }

        $this->logger->warning('Transaction description exceeds the gateway limit and was truncated', [
            'configDomain' => $configDomain,
            'salesChannelId' => $salesChannelId,
            'maxLengthBytes' => $maxLengthBytes,
            'actualLengthBytes' => strlen($rendered),
        ]);

        // mb_strcut() counts bytes like the gateway does, but never splits a multibyte character.
        return $this->trimSeparators(mb_strcut($rendered, 0, $maxLengthBytes, 'UTF-8'), trailingOnly: true);
    }

    private function trimSeparators(string $value, bool $trailingOnly = false): string
    {
        $pattern = $trailingOnly
            ? '/' . self::SEPARATOR_RUN . '$/u'
            : '/^' . self::SEPARATOR_RUN . '|' . self::SEPARATOR_RUN . '$/u';

        // preg_replace() returns null on malformed UTF-8; keep the input rather than blanking the
        // description over an encoding problem that came from somewhere else.
        return preg_replace($pattern, '', $value) ?? $value;
    }

    private function resolveSalesChannelName(?string $salesChannelId): string
    {
        if ($salesChannelId === null || $salesChannelId === '') {
            return '';
        }

        $criteria = new Criteria([$salesChannelId]);
        $salesChannel = $this->salesChannelRepository->search($criteria, Context::createDefaultContext())->first();

        return $salesChannel?->getName() ?? '';
    }
}
