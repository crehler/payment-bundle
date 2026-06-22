<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\BlikPayment;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final readonly class BlikPaymentRequestDTO
{
    /**
     * @param array<string, mixed>|null $customFields
     */
    public function __construct(
        public string $paymentMethodId,
        public string $blikCode,
        public SalesChannelContext $salesChannelContext,
        public Request $request,
        public ?string $finishUrl = null,
        public ?string $errorUrl = null,
        public ?array $customFields = null,
    ) {
    }
}
