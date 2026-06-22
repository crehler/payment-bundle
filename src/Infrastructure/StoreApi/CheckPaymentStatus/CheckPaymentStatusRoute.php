<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CheckPaymentStatus;

use Crehler\PaymentBundle\Application\Service\CheckPayment\CheckPaymentService;
use Crehler\PaymentBundle\Infrastructure\Struct\CheckPaymentStatusStruct;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Autoconfigure(public: true)]
class CheckPaymentStatusRoute extends AbstractCheckPaymentStatusRoute
{
    public function __construct(
        private readonly CheckPaymentService $checkPaymentService,
    ) {
    }

    public function getDecorated(): AbstractCheckPaymentStatusRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/cr/payment/check',
        name: 'store-api.cr.payment.check',
        methods: ['POST'],
        defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true]
    )]
    public function check(CheckPaymentStatusRequest $request, SalesChannelContext $context): CheckPaymentStatusResponse
    {
        $response = $this->checkPaymentService->execute(request: $request, context: $context);
        $paymentStatus = $response->paymentStatus;

        $struct = new CheckPaymentStatusStruct(
            status: $paymentStatus->isPaid,
            waiting: $paymentStatus->isWaiting,
            failed: $paymentStatus->hasFailed()
        );

        return new CheckPaymentStatusResponse(object: $struct);
    }
}
