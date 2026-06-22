<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Mapper\Payment;

use Crehler\PaymentBundle\Application\DTO\PaymentSubMethodDTO;
use Crehler\PaymentBundle\Infrastructure\Struct\PaymentSubMethod\PaymentSubMethodStruct;

final readonly class PaymentSubMethodMapper
{
    public function toStruct(PaymentSubMethodDTO $dto): PaymentSubMethodStruct
    {
        return new PaymentSubMethodStruct(
            $dto->name,
            $dto->providerId,
            $dto->shopwareId,
            $dto->mediaUrl
        );
    }

    public function toDto(PaymentSubMethodStruct $struct): PaymentSubMethodDTO
    {
        return new PaymentSubMethodDTO(
            $struct->name,
            $struct->providerId,
            $struct->shopwareId,
            $struct->mediaUrl
        );
    }
}
