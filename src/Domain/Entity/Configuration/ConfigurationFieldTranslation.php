<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity\Configuration;

use Attribute;
use JsonSerializable;

#[Attribute]
final readonly class ConfigurationFieldTranslation implements JsonSerializable
{
    public const PL = 'pl-PL';
    public const EN = 'en-GB';
    public const DE = 'de-DE';

    public function __construct(
        public string $language,
        public string $title,
        public ?string $helpText = null,
        public ?string $placeholder = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'language' => $this->language,
            'title' => $this->title,
            'helpText' => $this->helpText,
            'placeholder' => $this->placeholder,
        ];
    }
}
