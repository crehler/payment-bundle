<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Domain\Contract\PaymentSubMethodPort;
use Crehler\PaymentBundle\Shared\{AmountFormat, EnhancedLogger};
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Throwable;

use function is_string;
use function strtolower;

/**
 * Decides whether a submitted channel code may be recorded against a payment method.
 *
 * The rules used to sit in SalesChannelContextSwitchSubscriber, which left that class both
 * handling the event and reaching for the gateway, the cart and the amount formatter to
 * answer a question of its own. They are two decisions, and neither needs an event:
 *
 * - is this value even about the method being set (or left over from the one being left),
 * - and does the provider currently offer it.
 *
 * Both are decisions about the payment domain, so they belong here; the subscriber keeps
 * what only it can do — reacting to the switch and persisting the result.
 */
final readonly class SubMethodSelectionValidator
{
    public function __construct(
        private PaymentSubMethodPort $paymentSubMethodPort,
        private CartService $cartService,
        private AmountFormat $amountFormat,
        private EnhancedLogger $logger,
    ) {
    }

    /**
     * Did the widget that produced this value belong to a different payment method?
     *
     * The channel widget lives inside Shopware's changePaymentForm, so switching the
     * payment method serialises whatever channel is still checked under the previous one.
     * That arrives as gpay-for-a-bank-transfer: stale, not invalid.
     *
     * Absent means an older client that does not send the pairing, or a Store API caller
     * setting the sub-method directly; both keep the strict behaviour, because treating a
     * missing pairing as "must be stale" would reopen the hole WT-910 closed.
     */
    public function isStaleForMethod(mixed $renderedFor, PaymentMethodEntity $paymentMethod): bool
    {
        if (!is_string($renderedFor) || $renderedFor === '') {
            return false;
        }

        // Hex ids may arrive upper-cased, same as paymentMethodId itself.
        return strtolower($renderedFor) !== strtolower($paymentMethod->getId());
    }

    /**
     * Is the code one the provider currently offers for this payment method?
     *
     * The cart total is passed as the payment value because providers filter banks by
     * amount limits; using 0 would reject perfectly valid banks that have a minimum.
     *
     * Normally free: the injected port is the cached decorator, and the storefront widget
     * has just fetched this exact list — same sales channel, payment method, amount,
     * currency and locale, so the same cache key. Clicking a bank therefore validates
     * against a warm cache instead of asking the gateway a second time, which is what used
     * to turn a slow gateway into a 400 on the context switch.
     */
    public function isOffered(
        PaymentMethodEntity $paymentMethod,
        string $subPaymentMethodId,
        SalesChannelContext $context,
    ): bool {
        if ($subPaymentMethodId === '') {
            return false;
        }

        try {
            $subMethods = $this->paymentSubMethodPort->getPaymentSubMethods(
                paymentMethodEntity: $paymentMethod,
                paymentValue: $this->cartTotalInMinorUnits($context),
                context: $context,
            );
        } catch (Throwable $e) {
            // Fail closed: without a list there is nothing to compare against, so reject
            // instead of letting an unvalidated value through. The value is sent to the
            // gateway and persisted on the customer account, so accepting it unchecked
            // costs a permanent bad record; a 400 the client can retry does not.
            //
            // This is now genuinely rare rather than the everyday slow-gateway case: the
            // cache above means a reachable-a-moment-ago gateway still answers from cache,
            // so landing here means the list could not be produced at all — in which case
            // the customer had nothing to pick from either.
            $this->logger->error('Could not load payment sub-methods for validation; rejecting value', [
                'paymentMethodId' => $paymentMethod->getId(),
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        foreach ($subMethods as $subMethod) {
            if ($subMethod->providerId === $subPaymentMethodId) {
                return true;
            }
        }

        return false;
    }

    private function cartTotalInMinorUnits(SalesChannelContext $context): int
    {
        try {
            // caching: false — the cart must be recalculated against the context after the
            // switch, because the sub-method amount limits are checked against this total.
            $cart = $this->cartService->getCart($context->getToken(), $context, caching: false);

            return $this->amountFormat->floatToInt($cart->getPrice()->getTotalPrice());
        } catch (Throwable) {
            return 0;
        }
    }
}
