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
use InvalidArgumentException;
use JsonSerializable;
use TypeError;

use function array_map;

#[Attribute]
readonly class ConfigurationField implements JsonSerializable
{
    public function __construct(
        public string $type,
        public string $title,
        public ?string $helpText = null,
        public ?string $placeholder = null,
        public bool $copyable = false,
        public array $translations = [],
    ) {
    }

    public function jsonSerialize(): array
    {
        try {
            $translations = array_map(fn (ConfigurationFieldTranslation $translation) => $translation->jsonSerialize(), $this->translations);
        } catch (TypeError $e) {
            throw new InvalidArgumentException('Translations in ' . self::class . ' must be an array of ' . ConfigurationFieldTranslation::class . ' objects');
        }

        return [
            'type' => $this->type,
            'title' => $this->title,
            'helpText' => $this->helpText,
            'placeholder' => $this->placeholder,
            'copyable' => $this->copyable,
            'translations' => $translations,
        ];
    }
}
