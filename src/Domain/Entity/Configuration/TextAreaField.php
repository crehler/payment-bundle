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

#[Attribute]
final readonly class TextAreaField extends ConfigurationField
{
    /**
     * @var string
     */
    public const TYPE = 'textarea';

    public function __construct(
        string $title,
        ?string $helpText = null,
        ?string $placeholder = null,
        bool $copyable = false,
        array $translations = [],
    ) {
        parent::__construct(
            type: self::TYPE,
            title: $title,
            helpText: $helpText,
            placeholder: $placeholder,
            copyable: $copyable,
            translations: $translations
        );
    }
}
