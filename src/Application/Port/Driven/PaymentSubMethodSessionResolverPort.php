<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Domain\Entity\Customer;
use Symfony\Component\HttpFoundation\Request;

interface PaymentSubMethodSessionResolverPort
{
    /**
     * Resolves the selected payment sub-method (e.g. bank for pay-by-link).
     *
     * Session is the source of truth (mirrors how Shopware reads the native
     * payment method at payment time). When the session is empty and a logged-in
     * customer is given, the value saved on the customer account is used as a
     * fallback — parity with native paymentMethodId, which Shopware restores from
     * the customer-bound context on login.
     */
    public function resolve(Request $request, string $paymentMethodId, ?Customer $customer = null): ?string;
}
