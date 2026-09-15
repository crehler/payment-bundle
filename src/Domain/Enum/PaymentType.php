<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Enum;

/**
 * The family a payment method belongs to, declared by its handler.
 *
 * One value per payment method, because the families are mutually exclusive: a method
 * is BLIK or a card or a pay-by-link, never two at once. The five booleans this replaces
 * (isBlik/isCard/isBank/isEwallet/isDeferred) described a state that could not be
 * combined, and were inferred from the handler class name — so anything named outside
 * the convention silently classified as nothing at all.
 *
 * This enum answers "what kind of method is this", a compile-time fact owned by the
 * handler. It deliberately says nothing about how many channels the method offers right
 * now: that is runtime data from the gateway and belongs nowhere near a declaration.
 */
enum PaymentType: string
{
    case BLIK = 'blik';
    case CARD = 'card';
    /** Pay-by-link: customer picks a bank and is redirected to it. */
    case PAY_BY_LINK = 'pbl';
    /** Electronic wallets: Google Pay, Apple Pay, Click to Pay, Visa Mobile. */
    case WALLET = 'wallet';
    /** Buy-now-pay-later: PayPo, Twisto, Klarna. */
    case DEFERRED = 'deferred';
    case INSTALMENTS = 'instalments';
    /** Classic bank transfer (ING wt / wt_split); not exposed as a method today. */
    case BANK_TRANSFER = 'bank_transfer';
    /** Payment straight from an account held at the gateway's own bank ("Płać z ING"). */
    case ACCOUNT = 'account';
    /**
     * The gateway's own method-selection page. The customer is redirected with no method
     * chosen in the shop and picks there, from whatever the merchant's contract covers —
     * including methods the plugin has no handler for.
     *
     * Distinct from PAY_BY_LINK with no channel picked, which is still a bank transfer and
     * lands on the operator's bank list. This one is the full paywall. It is also the reason
     * the case has to exist rather than borrowing PAY_BY_LINK: PaymentSubMethodAdapter
     * matches providers on the type alone, so a paywall method declared as PAY_BY_LINK would
     * be served a bank list it ignores.
     *
     * No provider serves channels for it and no template branches on it — the declaration's
     * whole job is to be neither BLIK nor CARD nor anything a sub-method provider claims.
     */
    case PAYWALL = 'paywall';
}
