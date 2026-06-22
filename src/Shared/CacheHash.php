<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Shared;

use function hash;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Utility class for generating cache hashes.
 */
final class CacheHash
{
    private function __construct()
    {
    }

    public static function hash(string $value): string
    {
        return hash('xxh32', $value);
    }

    public static function hashArray(array $values): string
    {
        return self::hash(json_encode($values, JSON_THROW_ON_ERROR));
    }
}
