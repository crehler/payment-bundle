<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Api;

use Crehler\PaymentBundle\Domain\Event\PaymentNotificationReceivedEvent;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Universal payment notification controller.
 *
 * This controller receives all payment notifications and dispatches an event.
 * Payment providers (PayNow, PayU, etc.) subscribe to the event and handle
 * notifications that match their expected format.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Autoconfigure(public: true)]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EnhancedLogger $logger,
    ) {
    }

    #[Route(
        path: 'payment/notification',
        name: 'crehler.bundle.payment.notification',
        defaults: ['auth_required' => false],
        methods: ['POST']
    )]
    public function handleNotification(Request $request, Context $context): Response
    {
        $event = new PaymentNotificationReceivedEvent(
            request: $request,
            context: $context
        );

        $this->eventDispatcher->dispatch($event, PaymentNotificationReceivedEvent::EVENT_NAME);

        if (!$event->isHandled()) {
            // Do not log the request body/query/headers here: the notify URL carries
            // the _sw_payment_token and gateway payloads may contain payment secrets.
            $this->logger->warning('Payment notification was not handled by any provider');

            return new Response('No handler found for this notification', Response::HTTP_BAD_REQUEST);
        }

        return new Response(
            $event->getResponseMessage() ?? 'OK',
            $event->getResponseCode()
        );
    }
}
