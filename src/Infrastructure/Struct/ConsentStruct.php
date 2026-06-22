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

final class ConsentStruct extends Struct
{
    public const API_ALIAS = 'cr_payment_consent';

    public function __construct(
        public readonly string $content,
        public readonly string $locale,
        public readonly ?string $title = null,
        /**
         * Whether this consent must be actively accepted (rendered as a required
         * checkbox). When false it is an informational disclosure — e.g. a GDPR
         * data-processing notice that must be shown but not ticked — and is
         * rendered as a collapsible info block instead of a required checkbox.
         */
        public readonly bool $requiresAcceptance = true,
    ) {
    }

    public function getApiAlias(): string
    {
        return self::API_ALIAS;
    }

    public function withTitle(string $title): self
    {
        return new self(
            content: $this->content,
            locale: $this->locale,
            title: $title,
            requiresAcceptance: $this->requiresAcceptance,
        );
    }
}
