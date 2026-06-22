<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Storefront\CheckPayment;

use Crehler\PaymentBundle\Infrastructure\StoreApi\CheckPaymentStatus\{AbstractCheckPaymentStatusRoute, CheckPaymentStatusRequest, CheckPaymentStatusResponse};
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class CheckPaymentStatusController extends StorefrontController
{
    public function __construct(private readonly AbstractCheckPaymentStatusRoute $checkPaymentStatusRoute)
    {
    }

    #[Route(
        path: '/cr/payment/check',
        name: 'frontend.cr.payment.check',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function handle(CheckPaymentStatusRequest $request, SalesChannelContext $salesChannelContext): CheckPaymentStatusResponse
    {
        return $this->checkPaymentStatusRoute->check(request: $request, context: $salesChannelContext);
    }
}
