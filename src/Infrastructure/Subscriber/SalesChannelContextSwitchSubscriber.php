<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Application\Service\CustomerPaymentSubMethod\CustomerPaymentSubMethodService;
use Crehler\PaymentBundle\Domain\Contract\CustomerRepositoryPort;
use Crehler\PaymentBundle\Domain\Entity\CustomerPaymentSubMethod\CustomerPaymentSubMethod;
use Crehler\PaymentBundle\Domain\Exception\DomainException;
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentSubMethodSessionResolver;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Mirrors how Shopware persists the native paymentMethodId on a context switch:
 * the selected sub-method (bank) is written to the session, and whenever a customer
 * is present on the context (logged-in or guest) additionally saved on the account.
 * This is the single entry point for setting the sub-method — there is no dedicated
 * write endpoint.
 */
readonly class SalesChannelContextSwitchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CustomerPaymentSubMethodService $customerPaymentSubMethodService,
        private CustomerRepositoryPort $customerRepositoryPort,
        private RequestStack $requestStack,
        private EnhancedLogger $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [SalesChannelContextSwitchEvent::class => 'onSalesChannelContextSwitch'];
    }

    public function onSalesChannelContextSwitch(SalesChannelContextSwitchEvent $event): void
    {
        if (!$event->getRequestDataBag()->has('paymentSubMethod')) {
            return;
        }

        $paymentMethodId = (string) $event->getRequestDataBag()->get('paymentMethodId');
        $subPaymentMethodId = (string) $event->getRequestDataBag()->get('paymentSubMethod');

        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $session = $this->requestStack->getSession();
            $session->set(PaymentSubMethodSessionResolver::SESSION_KEY_PREFIX . $paymentMethodId, $subPaymentMethodId);
        }

        // A guest is a real customer row (guest=1), so its choice is persisted on the
        // account too — handle-payment reads it back via order.orderCustomer.customer.
        // Only a fully anonymous context (no customer yet) has nowhere to persist.
        $customerEntity = $event->getSalesChannelContext()->getCustomer();
        if ($customerEntity === null) {
            return;
        }

        try {
            $customer = $this->customerRepositoryPort->getFromCustomerEntity(customerEntity: $customerEntity);
            $this->customerPaymentSubMethodService->setSubMethod(
                customer: $customer,
                customerPaymentSubMethod: new CustomerPaymentSubMethod(
                    paymentMethodId: $paymentMethodId,
                    subPaymentMethodId: $subPaymentMethodId
                ),
                context: $event->getSalesChannelContext()->getContext()
            );
        } catch (DomainException $e) {
            // Persisting the preference must never break the context switch / checkout.
            $this->logger->error(message: $e->getMessage(), context: ['exception' => $e]);
        }
    }
}
