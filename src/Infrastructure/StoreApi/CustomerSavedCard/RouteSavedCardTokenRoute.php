<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard;

use Crehler\PaymentBundle\Domain\Contract\SavedCardTokenServicePort;
use Crehler\PaymentBundle\Domain\Entity\SavedCardCollection;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard\Abstract\AbstractSavedCardTokenRoute;
use Crehler\PaymentBundle\Infrastructure\Struct\Card\SavedCardCollection as ShopwareSavedCardCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\{Autoconfigure, AutoconfigureTag};
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Autoconfigure(public: true)]
#[AutoconfigureTag('controller.service_arguments')]
class RouteSavedCardTokenRoute extends AbstractSavedCardTokenRoute
{
    public function __construct(
        private SavedCardTokenServicePort $savedCardTokenServicePort,
    ) {
    }

    public function getDecorated(): AbstractSavedCardTokenRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/cr/payment/card-tokens',
        name: 'store-api.cr.payment.card-tokens',
        methods: ['GET'],
        defaults: ['_loginRequired' => true]
    )]
    public function getCustomerCardTokens(PaymentMethodEntity $paymentMethod, SalesChannelContext $context): SavedCardTokenResponse
    {
        $response = new SavedCardTokenResponse(new ShopwareSavedCardCollection());
        $customer = $context->getCustomer();

        if ($customer->getGuest()) {
            return $response;
        }

        $savedCardTokens = $this->savedCardTokenServicePort->getSavedCards(
            paymentMethodId: $paymentMethod->getId(),
            context: $context
        );

        $shopwareCollection = $this->mapToShopwareCollection(savedCardTokens: $savedCardTokens);

        return new SavedCardTokenResponse(object: $shopwareCollection);
    }

    private function mapToShopwareCollection(SavedCardCollection $savedCardTokens): ShopwareSavedCardCollection
    {
        $shopwareCollection = new ShopwareSavedCardCollection();

        // SavedCardCollection is a plain DTO (not Traversable) — iterate its inner array,
        // not the object itself (which would yield the array, not each SavedCard).
        foreach ($savedCardTokens->savedCards as $savedCardToken) {
            $shopwareCollection->add(element: $savedCardToken);
        }

        return $shopwareCollection;
    }
}
