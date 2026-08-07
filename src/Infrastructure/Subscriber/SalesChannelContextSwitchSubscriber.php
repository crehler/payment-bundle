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
use Crehler\PaymentBundle\Domain\Contract\{CustomerRepositoryPort, PaymentSubMethodPort};
use Crehler\PaymentBundle\Domain\Entity\CustomerPaymentSubMethod\CustomerPaymentSubMethod;
use Crehler\PaymentBundle\Domain\Exception\DomainException;
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentSubMethodSessionResolver;
use Crehler\PaymentBundle\Shared\{AmountFormat, EnhancedLogger};
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

use function is_scalar;
use function strtolower;

/**
 * Mirrors how Shopware persists the native paymentMethodId on a context switch:
 * the selected sub-method (bank) is written to the session, and whenever a customer
 * is present on the context (logged-in or guest) additionally saved on the account.
 * This is the single entry point for setting the sub-method — there is no dedicated
 * write endpoint.
 *
 * Because it is the only entry point, it is also the only place that can validate:
 * the value is sent straight to the gateway later on, and it is persisted on the
 * customer account, so an unchecked value is a permanent bad record (WT-910 pushed
 * a non-existent bank, 300 characters, a number, an SQL-like string and an HTML
 * script fragment through it). Only sub-method codes the provider actually offers
 * for the given payment method are accepted.
 */
readonly class SalesChannelContextSwitchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CustomerPaymentSubMethodService $customerPaymentSubMethodService,
        private CustomerRepositoryPort $customerRepositoryPort,
        private PaymentSubMethodPort $paymentSubMethodPort,
        private CartService $cartService,
        private AmountFormat $amountFormat,
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
        $dataBag = $event->getRequestDataBag();

        if (!$dataBag->has('paymentSubMethod')) {
            return;
        }

        $salesChannelContext = $event->getSalesChannelContext();

        // A non-scalar (paymentSubMethod[]=x) would become "Array" under a string cast.
        $rawSubMethod = $dataBag->get('paymentSubMethod');
        if (!is_scalar($rawSubMethod)) {
            throw RoutingException::invalidRequestParameter('paymentSubMethod');
        }

        $paymentMethodId = (string) $dataBag->get('paymentMethodId');
        $subPaymentMethodId = (string) $rawSubMethod;

        // The switch may set the payment method in the same request, so validate against
        // the method being switched to; only fall back to the context when absent.
        $paymentMethod = $this->resolvePaymentMethod($paymentMethodId, $salesChannelContext);

        if ($paymentMethod === null || !$this->isOfferedSubMethod($paymentMethod, $subPaymentMethodId, $salesChannelContext)) {
            throw RoutingException::invalidRequestParameter('paymentSubMethod');
        }

        // Persist on the account FIRST when there is one: the session used to be written
        // up front and a failing persist was only logged, so the session and the account
        // silently disagreed about the selected bank from then on.
        $customerEntity = $salesChannelContext->getCustomer();

        if ($customerEntity !== null) {
            try {
                $customer = $this->customerRepositoryPort->getFromCustomerEntity(customerEntity: $customerEntity);
                $this->customerPaymentSubMethodService->setSubMethod(
                    customer: $customer,
                    customerPaymentSubMethod: new CustomerPaymentSubMethod(
                        paymentMethodId: $paymentMethod->getId(),
                        subPaymentMethodId: $subPaymentMethodId
                    ),
                    context: $salesChannelContext->getContext()
                );
            } catch (DomainException $e) {
                // Persisting the preference must never break the context switch / checkout,
                // but then the session must not claim a choice the account does not have.
                $this->logger->error(message: $e->getMessage(), context: ['exception' => $e]);

                return;
            }
        }

        $this->writeToSession($paymentMethod->getId(), $subPaymentMethodId);
    }

    private function writeToSession(string $paymentMethodId, string $subPaymentMethodId): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null || !$request->hasSession()) {
            return;
        }

        $this->requestStack->getSession()->set(
            PaymentSubMethodSessionResolver::SESSION_KEY_PREFIX . $paymentMethodId,
            $subPaymentMethodId,
        );
    }

    private function resolvePaymentMethod(string $paymentMethodId, SalesChannelContext $context): ?PaymentMethodEntity
    {
        $contextPaymentMethod = $context->getPaymentMethod();

        // Hex ids may arrive upper-cased; compare case-insensitively so a legitimate
        // combined switch (method + sub-method in one PATCH) is not rejected.
        if ($paymentMethodId === '' || strtolower($paymentMethodId) === strtolower($contextPaymentMethod->getId())) {
            return $contextPaymentMethod;
        }

        // Shopware validates and applies paymentMethodId itself on this event, so the
        // context already carries the switched-to method by the time we run. A different
        // id here means the switch did not resolve to it — reject rather than guess.
        return null;
    }

    /**
     * Is the code one the provider currently offers for this payment method?
     *
     * The cart total is passed as the payment value because providers filter banks by
     * amount limits; using 0 would reject perfectly valid banks that have a minimum.
     */
    private function isOfferedSubMethod(
        PaymentMethodEntity $paymentMethod,
        string $subPaymentMethodId,
        SalesChannelContext $context,
    ): bool {
        if ($subPaymentMethodId === '') {
            return false;
        }

        try {
            $subMethods = $this->paymentSubMethodPort->getPaymentSubMethods(
                paymentMethodEntity: $paymentMethod,
                paymentValue: $this->cartTotalInMinorUnits($context),
                context: $context,
            );
        } catch (Throwable $e) {
            // Fail closed: without a list there is nothing to compare against, so reject
            // instead of letting an unvalidated value through. The client gets a 400 and
            // retries, which is cheaper than a permanently wrong record on the account.
            // Accepting would not save a working checkout either — with the provider down
            // the storefront has nothing to render the bank list from in the first place.
            $this->logger->error('Could not load payment sub-methods for validation; rejecting value', [
                'paymentMethodId' => $paymentMethod->getId(),
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        foreach ($subMethods as $subMethod) {
            if ($subMethod->providerId === $subPaymentMethodId) {
                return true;
            }
        }

        return false;
    }

    private function cartTotalInMinorUnits(SalesChannelContext $context): int
    {
        try {
            // caching: false — the cart must be recalculated against the context after the
            // switch, because the sub-method amount limits are checked against this total.
            $cart = $this->cartService->getCart($context->getToken(), $context, caching: false);

            return $this->amountFormat->floatToInt($cart->getPrice()->getTotalPrice());
        } catch (Throwable) {
            return 0;
        }
    }
}
