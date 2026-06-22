<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Shared;

use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\{ArrayDenormalizer, ObjectNormalizer};
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

final class Serializer
{
    /**
     * @var string
     */
    public const JSON_FORMAT = 'json';
    private static ?SymfonySerializer $serializer = null;

    public static function getSerializer(): SymfonySerializer
    {
        if (self::$serializer === null) {
            $encoders = [new JsonEncoder()];
            $normalizers = [new ArrayDenormalizer(), new ObjectNormalizer(
                null,
                null,
                null,
                new ReflectionExtractor()
            )];

            self::$serializer = new SymfonySerializer($normalizers, $encoders);
        }

        return self::$serializer;
    }
}
