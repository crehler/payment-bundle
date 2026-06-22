<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Api\ConnectionTest;

use Crehler\PaymentBundle\Application\Port\Driven\GatewayConnectionCheckerInterface;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Symfony\Component\DependencyInjection\Attribute\{Autoconfigure, AutowireIterator};
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

use function in_array;
use function is_array;
use function is_string;
use function json_decode;

/**
 * Single shared endpoint backing the admin "test connection" component across all
 * provider plugins. The component posts the UNSAVED form values, so credentials are
 * validated before save and never travel in the URL/query (POST body only).
 *
 * Dispatches to the GatewayConnectionCheckerInterface implementation matching the
 * plugin's config domain. Always reflects the real outcome via HTTP status
 * (200 ok / 400 failed), so the front-end notification can't show a false success.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Autoconfigure(public: true)]
final class ConnectionTestController
{
    private const ENVIRONMENTS = ['live', 'sandbox'];

    /**
     * @param iterable<GatewayConnectionCheckerInterface> $checkers
     */
    public function __construct(
        #[AutowireIterator(GatewayConnectionCheckerInterface::class)]
        private readonly iterable $checkers,
        private readonly EnhancedLogger $logger,
    ) {
    }

    #[Route(
        path: '/api/_action/crehler-payment/test-connection',
        name: 'api.action.crehler-payment.test-connection',
        methods: ['POST'],
    )]
    public function testConnection(Request $request): JsonResponse
    {
        // The admin httpClient posts JSON, so the body lives in the content, not in
        // $request->request (which only parses form-encoded payloads).
        $payload = json_decode($request->getContent(), true);
        $payload = is_array($payload) ? $payload : [];

        $configDomain = is_string($payload['configDomain'] ?? null) ? $payload['configDomain'] : '';
        $environment = is_string($payload['environment'] ?? null) ? $payload['environment'] : '';
        $config = $payload['config'] ?? [];
        $salesChannelId = (is_string($payload['salesChannelId'] ?? null) && $payload['salesChannelId'] !== '')
            ? $payload['salesChannelId']
            : null;

        if ($configDomain === '' || !in_array($environment, self::ENVIRONMENTS, true)) {
            return new JsonResponse(
                ['success' => false, 'message' => 'Invalid request: configDomain and environment are required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!is_array($config)) {
            $config = [];
        }

        $checker = $this->resolveChecker($configDomain);

        if ($checker === null) {
            return new JsonResponse(
                ['success' => false, 'message' => 'No connection checker registered for this payment provider.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $result = $checker->check($environment, $config, $salesChannelId);
        } catch (Throwable $e) {
            $this->logger->warning('Gateway connection test failed', [
                'configDomain' => $configDomain,
                'environment' => $environment,
                'salesChannelId' => $salesChannelId,
                'exceptionClass' => $e::class,
                'exceptionCode' => $e->getCode(),
            ]);

            return new JsonResponse(
                ['success' => false, 'message' => 'Connection test failed. Check the logs for details.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse(
            ['success' => $result->success, 'message' => $result->message],
            $result->success ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST,
        );
    }

    private function resolveChecker(string $configDomain): ?GatewayConnectionCheckerInterface
    {
        foreach ($this->checkers as $checker) {
            if ($checker->supports($configDomain)) {
                return $checker;
            }
        }

        return null;
    }
}
