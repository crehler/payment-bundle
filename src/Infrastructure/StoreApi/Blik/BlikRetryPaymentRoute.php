<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\Blik;

use Crehler\PaymentBundle\Application\DTO\BlikPayment\BlikRetryPaymentRequestDTO;
use Crehler\PaymentBundle\Application\Service\BlikPayment\BlikRetryPaymentService;
use Crehler\PaymentBundle\Domain\Exception\DomainException;
use Crehler\PaymentBundle\Infrastructure\StoreApi\Blik\Abstract\AbstractBlikRetryPaymentRoute;
use Crehler\PaymentBundle\Infrastructure\Struct\BlikPaymentStruct;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use function preg_match;
use function preg_replace;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[AsAlias(AbstractBlikRetryPaymentRoute::class)]
final class BlikRetryPaymentRoute extends AbstractBlikRetryPaymentRoute
{
    public function __construct(
        private readonly BlikRetryPaymentService $blikRetryPaymentService,
        private readonly RateLimiter $rateLimiter,
        private readonly EnhancedLogger $logger,
    ) {
    }

    public function getDecorated(): AbstractBlikRetryPaymentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/cr/payment/blik/order/{orderId}',
        name: 'store-api.cr.payment.blik.order',
        methods: ['POST'],
        defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true]
    )]
    public function pay(string $orderId, Request $request, SalesChannelContext $context): BlikPaymentRouteResponse
    {
        // Throttle per session+order before any work. Throws HTTP 429 once exceeded.
        $this->rateLimiter->ensureAccepted('cr_blik_payment', $context->getToken() . '-' . $orderId);

        try {
            $blikCode = preg_replace('/\s+/', '', $request->request->getString('blikCode')) ?? '';

            if (!preg_match('/^\d{6}$/', $blikCode)) {
                return new BlikPaymentRouteResponse(
                    new BlikPaymentStruct(
                        success: false,
                        error: 'Invalid BLIK code format',
                        orderId: $orderId,
                    ),
                );
            }

            $finishUrl = $request->request->getString('finishUrl') ?: null;
            $errorUrl = $request->request->getString('errorUrl') ?: null;

            $request->request->set('blikCode', $blikCode);

            $requestDTO = new BlikRetryPaymentRequestDTO(
                orderId: $orderId,
                blikCode: $blikCode,
                salesChannelContext: $context,
                request: $request,
                finishUrl: $finishUrl,
                errorUrl: $errorUrl,
            );

            $responseDTO = $this->blikRetryPaymentService->process(requestDTO: $requestDTO);

            if (!$responseDTO->success) {
                $this->logger->error($responseDTO->errorMessage ?? 'BLIK retry failed');

                return new BlikPaymentRouteResponse(
                    new BlikPaymentStruct(
                        success: false,
                        error: $responseDTO->errorMessage,
                        orderId: $responseDTO->orderId,
                    ),
                );
            }

            return new BlikPaymentRouteResponse(
                new BlikPaymentStruct(
                    success: true,
                    redirectUrl: $responseDTO->redirectUrl,
                    orderId: $responseDTO->orderId,
                ),
            );
        } catch (DomainException $e) {
            // Log detail server-side; return a generic message to the client.
            $this->logger->critical($e->getMessage(), ['exception' => $e]);

            return new BlikPaymentRouteResponse(
                new BlikPaymentStruct(
                    success: false,
                    error: 'BLIK retry failed',
                    orderId: $orderId,
                ),
            );
        }
    }
}
