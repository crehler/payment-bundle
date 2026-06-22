<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\Blik;

use Crehler\PaymentBundle\Application\DTO\BlikPayment\BlikPaymentRequestDTO;
use Crehler\PaymentBundle\Application\Service\BlikPayment\BlikPaymentService;
use Crehler\PaymentBundle\Domain\Exception\DomainException;
use Crehler\PaymentBundle\Infrastructure\StoreApi\Blik\Abstract\AbstractBlikPaymentRoute;
use Crehler\PaymentBundle\Infrastructure\Struct\BlikPaymentStruct;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use function count;
use function is_scalar;
use function is_string;
use function preg_match;
use function preg_replace;
use function str_starts_with;
use function strlen;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[AsAlias(AbstractBlikPaymentRoute::class)]
final class BlikPaymentRoute extends AbstractBlikPaymentRoute
{
    /** Reserved prefix for the bundle's own order custom fields — clients may not set these. */
    private const RESERVED_CUSTOM_FIELD_PREFIX = 'crehler_payment_';

    private const MAX_CUSTOM_FIELDS = 20;

    private const MAX_CUSTOM_FIELD_VALUE_LENGTH = 255;

    public function __construct(
        private readonly BlikPaymentService $blikPaymentService,
        private readonly RateLimiter $rateLimiter,
        private readonly EnhancedLogger $logger,
    ) {
    }

    public function getDecorated(): AbstractBlikPaymentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/cr/payment/blik',
        name: 'store-api.cr.payment.blik',
        methods: ['POST'],
        defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true]
    )]
    public function pay(Request $request, SalesChannelContext $context): BlikPaymentRouteResponse
    {
        // Throttle code submission per session before any work / order creation.
        // Throws RateLimitExceededException (HTTP 429) once the limit is hit.
        $this->rateLimiter->ensureAccepted('cr_blik_payment', $context->getToken());

        try {
            $paymentMethodId = $request->request->getString('paymentMethodId');
            $blikCode = preg_replace('/\s+/', '', $request->request->getString('blikCode')) ?? '';

            // Validate the BLIK code shape up-front so a malformed code never reaches
            // the gateway SDK and never triggers order creation (avoids orphan orders).
            if (!preg_match('/^\d{6}$/', $blikCode)) {
                return new BlikPaymentRouteResponse(
                    new BlikPaymentStruct(
                        success: false,
                        error: 'Invalid BLIK code format',
                    )
                );
            }

            $finishUrl = $request->request->getString('finishUrl') ?: null;
            $errorUrl = $request->request->getString('errorUrl') ?: null;
            $customFields = $this->sanitizeCustomFields($request->request->all('customFields'));

            $requestDTO = new BlikPaymentRequestDTO(
                paymentMethodId: $paymentMethodId,
                blikCode: $blikCode,
                salesChannelContext: $context,
                request: $request,
                finishUrl: $finishUrl,
                errorUrl: $errorUrl,
                customFields: $customFields,
            );

            $responseDTO = $this->blikPaymentService->process(requestDTO: $requestDTO);

            if (!$responseDTO->success) {
                $this->logger->error($responseDTO->errorMessage);

                return new BlikPaymentRouteResponse(
                    new BlikPaymentStruct(
                        success: false,
                        error: $responseDTO->errorMessage,
                        orderId: $responseDTO->orderId
                    )
                );
            }

            return new BlikPaymentRouteResponse(
                new BlikPaymentStruct(
                    success: true,
                    redirectUrl: $responseDTO->redirectUrl,
                    orderId: $responseDTO->orderId
                )
            );
        } catch (DomainException $e) {
            // Log the detail server-side; return a generic message so gateway/SDK
            // internals are never disclosed to the client.
            $this->logger->critical($e->getMessage(), ['exception' => $e]);

            return new BlikPaymentRouteResponse(
                new BlikPaymentStruct(
                    success: false,
                    error: 'BLIK payment failed'
                )
            );
        }
    }

    /**
     * Allow-list client-supplied order custom fields before they are persisted.
     * Prevents mass-assignment: blocks the bundle's reserved keys (so a client
     * cannot overwrite the return URLs / gateway id and hijack the payment flow),
     * enforces a safe key format, scalar values, and conservative size caps.
     *
     * @param array<array-key, mixed> $customFields
     *
     * @return array<string, scalar>|null
     */
    private function sanitizeCustomFields(array $customFields): ?array
    {
        $clean = [];

        foreach ($customFields as $key => $value) {
            if (!is_string($key)
                || preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $key) !== 1
                || str_starts_with($key, self::RESERVED_CUSTOM_FIELD_PREFIX)
                || !is_scalar($value)
            ) {
                continue;
            }

            if (is_string($value) && strlen($value) > self::MAX_CUSTOM_FIELD_VALUE_LENGTH) {
                continue;
            }

            $clean[$key] = $value;

            if (count($clean) >= self::MAX_CUSTOM_FIELDS) {
                break;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
