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

    public function __construct(
        public readonly bool $isBankPayment = false,
        public readonly bool $isBlikPayment = false,
        public readonly bool $isCardPayment = false,
        public readonly ?ConsentStruct $consent = null,
        public readonly bool $embedCardForm = false,
        public readonly string $blikInputPosition = 'checkout',
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
        );
    }
}
