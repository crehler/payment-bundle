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
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

use function is_string;

/**
 * Resolves the CheckPaymentStatusRequest argument, rejecting bad input as HTTP 400.
 *
 * Every invalid shape used to surface as a server error (WT-910): a missing orderId
 * returned an empty iterable, which left the controller's non-nullable argument
 * unresolvable; an empty string reached the query as an empty id list; and an array
 * or object hit the DTO's `string` property as a TypeError. All three are client
 * errors, so they answer 400 through Shopware's routing exceptions and land in the
 * standard Store API error envelope.
 */
class CheckPaymentStatusValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== CheckPaymentStatusRequest::class) {
            return [];
        }

        $orderId = $request->get('orderId');

        if ($orderId === null) {
            throw RoutingException::missingRequestParameter('orderId');
        }

        // Anything non-string (orderId[]=x, orderId[a]=b) or not a valid UUID would
        // otherwise fail deeper as a TypeError or inside the DAL criteria.
        if (!is_string($orderId) || !Uuid::isValid($orderId)) {
            throw RoutingException::invalidRequestParameter('orderId');
        }

        // Final "reconcile" poll (sent by the storefront once the wait window
        // elapses) — only then do we ask the gateway for a paid/not-booked mismatch.
        $reconcile = $request->get('reconcile', false) === true;

        yield new CheckPaymentStatusRequest(orderId: $orderId, reconcile: $reconcile);
    }
}
