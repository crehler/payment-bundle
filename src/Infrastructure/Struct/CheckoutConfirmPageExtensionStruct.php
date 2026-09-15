<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Page-level state for the checkout confirm page, describing the CURRENTLY SELECTED
 * payment method only.
 *
 * A flag named after the bank family used to live here and gate the sub-method selector in
 * Twig. It was set for the bank, wallet and deferred families at once — named for one of
 * them, covering three — and derived from a class-name guess, so wallet and instalment
 * handlers fell through to false and their channel lists never rendered. The gate now asks
 * the method's own contract (crPaymentContract.usesGatewayChannels) plus the number of
 * channels actually returned, so nothing page-level is needed for it.
 */
final class CheckoutConfirmPageExtensionStruct extends Struct
{
    public const API_ALIAS = 'cr_payment_checkout_confirm_extension';

    /**
     * @param string|null $cardFormTemplate Twig path of the selected provider's card form,
     *                                      resolved from CardFormTemplateProviderPort. Null
     *                                      when the selected method is not a card or the
     *                                      provider ships no form.
     */
    public function __construct(
        public readonly bool $isBlikPayment = false,
        public readonly bool $isCardPayment = false,
        public readonly ?ConsentStruct $consent = null,
        public readonly bool $embedCardForm = false,
        public readonly string $blikInputPosition = 'checkout',
        public readonly ?string $cardFormTemplate = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return self::API_ALIAS;
    }

    public function withConsent(?ConsentStruct $consent): self
    {
        return new self(
            $this->isBlikPayment,
            $this->isCardPayment,
            $consent,
            $this->embedCardForm,
            $this->blikInputPosition,
            $this->cardFormTemplate,
        );
    }

    public function withBundleConfig(bool $embedCardForm, string $blikInputPosition): self
    {
        return new self(
            $this->isBlikPayment,
            $this->isCardPayment,
            $this->consent,
            $embedCardForm,
            $blikInputPosition,
            $this->cardFormTemplate,
        );
    }

    public function withCardFormTemplate(?string $cardFormTemplate): self
    {
        return new self(
            $this->isBlikPayment,
            $this->isCardPayment,
            $this->consent,
            $this->embedCardForm,
            $this->blikInputPosition,
            $cardFormTemplate,
        );
    }
}
