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
        public readonly bool $isBankPayment = false,
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
            $this->isBankPayment,
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
            $this->isBankPayment,
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
            $this->isBankPayment,
            $this->isBlikPayment,
            $this->isCardPayment,
            $this->consent,
            $this->embedCardForm,
            $this->blikInputPosition,
            $cardFormTemplate,
        );
    }
}
