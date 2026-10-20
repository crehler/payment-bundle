<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Infrastructure\Api;

use Crehler\PaymentBundle\Infrastructure\Api\{NotificationController, PaymentReturnController};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The URLs handed to gateways (return, notification) must carry the sales channel's
 * base URL — /en, /pl — or a shop whose every domain has a path suffix receives the
 * customer on a URL that matches no sales channel (WT-1029, sklep.deante.pl).
 *
 * Shopware's storefront router inserts that base URL only for route names starting with
 * frontend., widgets. or payment., so the route NAME is what decides it. A rename back to
 * a custom prefix compiles, passes every other test and silently breaks those shops.
 */
final class GatewayFacingRouteNameTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function controllers(): iterable
    {
        yield 'payment return' => [PaymentReturnController::class];
        yield 'notification' => [NotificationController::class];
    }

    /**
     * @param class-string $controller
     */
    #[DataProvider('controllers')]
    public function testTheRouteNameGetsTheSalesChannelBaseUrl(string $controller): void
    {
        $names = [];
        foreach ((new ReflectionClass($controller))->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $names[] = (string) ($attribute->getArguments()['name'] ?? '');
            }
        }

        self::assertNotEmpty($names);

        foreach ($names as $name) {
            self::assertMatchesRegularExpression('/^(frontend|widgets|payment)\./', $name);
        }
    }
}
