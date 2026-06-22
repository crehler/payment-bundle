<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Storefront\Blik;

use Crehler\PaymentBundle\Application\Service\BlikAuthorizeService;
use Crehler\PaymentBundle\Application\Service\Security\OrderOwnershipGuard;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use function is_string;
use function preg_match;
use function str_starts_with;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class BlikPaymentAuthorizeRoute extends StorefrontController
{
    public function __construct(
        private readonly CheckoutConfirmPageLoader $finishPageLoader,
        private readonly BlikAuthorizeService $blikAuthorizeService,
        private readonly OrderOwnershipGuard $orderOwnershipGuard,
    ) {
    }

    #[Route(
        path: '/cr/blik/authorize',
        name: 'frontend.cr.blik.authorize',
        methods: ['GET']
    )]
    public function handle(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $transactionId = $request->query->get('transactionId');

        if (!is_string($transactionId) || !preg_match('/^[0-9a-fA-F]{32}$/', $transactionId)) {
            throw new NotFoundHttpException();
        }

        // The browser navigates to $target on success (window.location.href); only allow
        // same-origin relative paths so a crafted ?target=https://evil.com can't hijack it.
        $target = $this->sanitizeInternalTarget($request->query->get('target'));
        // Set when the code was already entered + authorized on checkout — the page then
        // skips the code-entry phase and polls the payment status directly.
        $authorized = $request->query->getBoolean('authorized');

        $orderTransaction = $this->blikAuthorizeService->execute(
            orderTransactionId: $transactionId,
            context: $salesChannelContext->getContext()
        );

        // Object-level authorization: the transaction's order must belong to the
        // current customer + sales channel. Respond 404 (not 403) so a foreign
        // transaction id cannot be used to confirm existence / read order data.
        if (!$this->orderOwnershipGuard->isTransactionOwnedByContext($orderTransaction, $salesChannelContext)) {
            throw new NotFoundHttpException();
        }

        $page = $this->finishPageLoader->load(request: $request, context: $salesChannelContext);

        $page->addExtension('order_transaction', $orderTransaction);

        return $this->renderStorefront(
            view: '@CrehlerPaymentBundle/storefront/page/authorize-blik-payment/index.html.twig',
            parameters: [
                'page' => $page,
                'target' => $target,
                'authorized' => $authorized,
            ]
        );
    }

    /**
     * Accept $target only when it is a same-origin relative path. Rejects absolute URLs,
     * protocol-relative ("//evil") and backslash ("/\evil") forms used for open redirects.
     */
    private function sanitizeInternalTarget(?string $target): ?string
    {
        if ($target === null || $target === '' || !str_starts_with($target, '/')) {
            return null;
        }

        if (str_starts_with($target, '//') || str_starts_with($target, '/\\')) {
            return null;
        }

        return $target;
    }
}
