<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\ValueResolver;

use Crehler\PaymentBundle\Infrastructure\StoreApi\CheckPaymentStatus\CheckPaymentStatusRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CheckPaymentStatusValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== CheckPaymentStatusRequest::class) {
            return [];
        }

        $orderId = $request->get('orderId');

        if ($orderId === null) {
            return [];
        }

        yield new CheckPaymentStatusRequest(orderId: $orderId);
    }
}
