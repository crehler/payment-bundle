<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard;

use Crehler\PaymentBundle\Application\Service\StoredCardService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\Routing\Attribute\Route;

/**
 * Shared Store-API endpoint to delete a customer's stored card by its opaque id.
 *
 * Ownership is enforced by {@see StoredCardService::deleteForCustomer()} (customer
 * + sales-channel scoped), so a hostile id cannot remove another customer's card.
 * Replaces the per-provider delete controllers that each re-implemented this check.
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Autoconfigure(public: true)]
class RouteSavedCardDeleteRoute
{
    public function __construct(
        private readonly StoredCardService $storedCardService,
    ) {
    }

    #[Route(
        path: '/store-api/cr/payment/saved-card/{id}',
        name: 'store-api.cr.payment.saved-card.delete',
        defaults: ['_loginRequired' => true],
        methods: ['DELETE']
    )]
    public function delete(string $id, SalesChannelContext $context): JsonResponse
    {
        $customer = $context->getCustomer();

        if ($customer === null || $customer->getGuest()) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_FORBIDDEN);
        }

        $deleted = $this->storedCardService->deleteForCustomer(
            id: $id,
            customerId: $customer->getId(),
            salesChannelId: $context->getSalesChannelId(),
            context: $context->getContext(),
        );

        // 404 (not 403) when the card is absent or not owned — avoids id enumeration.
        if (!$deleted) {
            return new JsonResponse(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
