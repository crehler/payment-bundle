<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Storefront\Blik;

use Crehler\PaymentBundle\Infrastructure\StoreApi\Blik\Abstract\AbstractBlikPaymentRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Autoconfigure(public: true)]
class BlikPaymentRouteProxy extends StorefrontController
{
    public function __construct(
        private readonly AbstractBlikPaymentRoute $route,
        private readonly RouterInterface $router,
    ) {
    }

    #[Route(
        path: '/cr/payment/blik',
        name: 'frontend.cr.payment.blik',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function load(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $response = $this->route->pay($request, $salesChannelContext);
        $blikPayment = $response->getBlikPayment();

        if ($blikPayment->success && $blikPayment->redirectUrl !== null) {
            return new JsonResponse([
                'redirect' => true,
                'location' => $blikPayment->redirectUrl,
            ]);
        }

        if ($blikPayment->orderId !== null) {
            $editOrderUrl = $this->router->generate('frontend.account.edit-order.page', [
                'orderId' => $blikPayment->orderId,
                'error-code' => 'CHECKOUT__BLIK_PAYMENT_FAILED',
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse([
                'redirect' => true,
                'location' => $editOrderUrl,
            ]);
        }

        return new JsonResponse([
            'redirect' => false,
            'message' => $blikPayment->error ?? 'Wystąpił błąd podczas przetwarzania płatności BLIK',
        ]);
    }
}
