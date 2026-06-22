<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Storefront\Blik;

use Crehler\PaymentBundle\Infrastructure\StoreApi\Blik\Abstract\AbstractBlikRetryPaymentRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Autoconfigure(public: true)]
class BlikEditOrderPaymentRoute extends StorefrontController
{
    public function __construct(
        private readonly AbstractBlikRetryPaymentRoute $route,
        private readonly RouterInterface $router,
    ) {
    }

    #[Route(
        path: '/cr/payment/blik/order/{orderId}',
        name: 'frontend.cr.payment.blik.order',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function pay(string $orderId, Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $response = $this->route->pay($orderId, $request, $salesChannelContext);
        $blikPayment = $response->getBlikPayment();

        if ($blikPayment->success && $blikPayment->redirectUrl !== null) {
            return new JsonResponse([
                'redirect' => true,
                'location' => $blikPayment->redirectUrl,
            ]);
        }

        if ($blikPayment->success) {
            $finishUrl = $this->router->generate('frontend.checkout.finish.page', [
                'orderId' => $orderId,
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse([
                'redirect' => true,
                'location' => $finishUrl,
            ]);
        }

        $errorUrl = $this->router->generate('frontend.account.edit-order.page', [
            'orderId' => $orderId,
            'error-code' => 'CHECKOUT__BLIK_PAYMENT_FAILED',
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse([
            'redirect' => true,
            'location' => $errorUrl,
        ]);
    }
}
