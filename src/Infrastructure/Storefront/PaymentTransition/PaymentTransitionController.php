<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Storefront\PaymentTransition;

use Crehler\PaymentBundle\Shared\UrlSigner;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use function is_string;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class PaymentTransitionController extends StorefrontController
{
    public function __construct(
        private readonly CheckoutFinishPageLoader $checkoutFinishPageLoader,
        private readonly UrlSigner $urlSigner,
    ) {
    }

    #[Route(
        path: '/cr/payment/transition',
        name: 'frontend.cr.payment.transition',
        methods: ['GET']
    )]
    public function transitionPage(Request $request, SalesChannelContext $context): Response
    {
        $orderId = $request->query->get('orderId');
        $target = $request->query->get('target');
        $signature = $request->query->get('sig');
        $providerLogo = $request->query->get('logo', 'default');

        // Open-redirect guard: the page JS does window.location.href = target, so the
        // target must be exactly the gateway URL the server produced. We only emit a
        // target the handler HMAC-signed; reject anything that doesn't verify (a
        // client-crafted ?target=https://evil.com has no valid signature → 404).
        if (!is_string($target) || $target === '' || !is_string($signature) || !$this->urlSigner->verify($target, $signature)) {
            throw new NotFoundHttpException();
        }

        // Load the finish page to get order data for GA tracking
        $page = $this->checkoutFinishPageLoader->load($request, $context);

        return $this->renderStorefront('@CrehlerPaymentBundle/storefront/page/payment-transition/index.html.twig', [
            'page' => $page,
            'target' => $target,
            'orderId' => $orderId,
            'providerLogo' => $providerLogo,
            'controllerName' => 'checkout',
            'controllerAction' => 'finishpage',
        ]);
    }
}
