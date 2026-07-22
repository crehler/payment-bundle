<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Resolver;

use Crehler\PaymentBundle\Application\Port\Driven\PaymentSubMethodSessionResolverPort;
use Crehler\PaymentBundle\Application\Service\CustomerPaymentSubMethod\CustomerPaymentSubMethodService;
use Crehler\PaymentBundle\Domain\Entity\Customer;
use Symfony\Component\HttpFoundation\Request;

final class PaymentSubMethodSessionResolver implements PaymentSubMethodSessionResolverPort
{
    /**
     * @var string
     */
    public const SESSION_KEY_PREFIX = 'crehler_payment_sub_method_';

    public function __construct(
        private readonly CustomerPaymentSubMethodService $customerPaymentSubMethodService,
    ) {
    }

    public function resolve(Request $request, string $paymentMethodId, ?Customer $customer = null): ?string
    {
        $fromSession = $request->hasSession()
            ? $request->getSession()->get(self::SESSION_KEY_PREFIX . $paymentMethodId)
            : null;

        if ($fromSession !== null) {
            return $fromSession;
        }

        // Session empty → fall back to the choice saved on the customer account.
        // The context switch persists the sub-method to the account for any customer
        // (logged-in or guest), so this gives parity with native paymentMethodId
        // (auto-restored) — crucial for /checkout/finish/order, which does not re-fire
        // the context switch and for guests may run on a fresh session/token.
        if ($customer !== null) {
            return $this->customerPaymentSubMethodService
                ->getSubMethod(customer: $customer, paymentMethodId: $paymentMethodId)
                ?->subPaymentMethodId;
        }

        return null;
    }
}
