<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Driven port a provider implements to contribute its own card form to the checkout
 * confirm page, when "embed card form in checkout" is enabled.
 *
 * Replaces the old Twig-block extension point, which did not work (WT-910). The bundle's
 * payment-form.html.twig must `sw_extends` the Storefront template at the SAME relative
 * path as its own, and in that situation Shopware flattens the inheritance chain: a
 * plugin extending the bundle's template at that path is resolved to the Storefront one
 * instead, so the plugin's override of `crehler_payment_method_card_form` never reached
 * the output and the section rendered empty. (The transition page was unaffected because
 * the bundle extends a different path there — hence one worked and the other did not.)
 *
 * Path collision also caps the block approach at one provider: with several payment
 * plugins installed, one shared template path resolves to a single template, and none of
 * the plugins called `{{ parent() }}`.
 *
 * Returning a path instead sidesteps both problems. Every provider ships its template
 * under its own namespace (e.g. `@CrehlerPayNow/storefront/component/payment/…`), so
 * there is nothing to collide and no inheritance chain to reason about.
 *
 * Single-winner: exactly one provider answers for a given payment handler identifier.
 */
#[AutoconfigureTag]
interface CardFormTemplateProviderPort
{
    /**
     * Does this provider own the card form for the given payment handler?
     */
    public function supports(string $handlerIdentifier): bool;

    /**
     * Twig path of the card form to embed, namespaced to the provider's own bundle.
     *
     * Rendered with `sw_include`, so the template receives the surrounding page context
     * and can use `context.paymentMethod` plus the payment-method extensions (saved card
     * tokens, sub-methods) that the bundle already attaches.
     */
    public function getTemplate(): string;
}
