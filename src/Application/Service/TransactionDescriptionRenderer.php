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
use Crehler\PaymentBundle\Infrastructure\Configuration\BundleConfigField;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use function array_keys;
use function array_values;
use function preg_replace;
use function str_contains;
use function str_replace;
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

    public function __construct(
        private SystemConfigService $systemConfigService,
        private EntityRepository $salesChannelRepository,
    ) {
    }

    /**
     * @param string $configDomain plugin config domain, e.g. "CrehlerTpay.config"
     */
    public function render(string $configDomain, Order $order, ?string $salesChannelId = null): string
    {
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
        ];

        // Resolve the sales channel name only when the template actually uses it.
        if (str_contains($template, self::TOKEN_SALES_CHANNEL_NAME)) {
            $replacements[self::TOKEN_SALES_CHANNEL_NAME] = $this->resolveSalesChannelName($order->salesChannelId);
        }

        $rendered = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Collapse whitespace left by empty tokens (e.g. missing sales channel name)
        // and trim stray separators so the gateway never receives "10001 - ".
        $rendered = (string) preg_replace('/\s{2,}/', ' ', $rendered);

        return trim($rendered, " \t\n\r\0\x0B-–—|·");
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
