<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSubMethods;

use Crehler\PaymentBundle\Application\Service\CustomerPaymentSubMethod\CustomerPaymentSubMethodService;
use Crehler\PaymentBundle\Domain\Contract\CustomerRepositoryPort;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSubMethods\Abstract\AbstractCustomerPaymentSubMethodRoute;
use Crehler\PaymentBundle\Infrastructure\Struct\PaymentSubMethod\PaymentCustomerSubMethodStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only endpoint exposing the customer's currently selected payment sub-method
 * (session first, then the value saved on the account). Used to pre-select the bank
 * in the UI. Setting the sub-method is done exclusively through the context switch
 * (PATCH /store-api/context with `paymentSubMethod`) — mirroring native paymentMethodId.
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
class CustomerPaymentSubMethodRoute extends AbstractCustomerPaymentSubMethodRoute
{
    private const SESSION_KEY_PREFIX = 'crehler_payment_sub_method_';

    public function __construct(
        private readonly CustomerPaymentSubMethodService $customerPaymentSubMethodService,
        private readonly CustomerRepositoryPort $customerRepositoryPort,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getDecorated(): AbstractCustomerPaymentSubMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/customer/cr/payment-sub-method',
        name: 'store-api.customer.cr.payment-sub-method.get',
        methods: ['GET'],
        defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true]
    )]
    public function get(PaymentMethodEntity $paymentMethodEntity, SalesChannelContext $context): PaymentCustomerSubMethodsRouteResponse
    {
        $paymentMethodId = $paymentMethodEntity->getId();
        $subPaymentMethodId = $this->resolveFromSession($paymentMethodId)
            ?? $this->resolveFromCustomer($paymentMethodId, $context);

        $paymentSubMethod = new PaymentCustomerSubMethodStruct(
            paymentMethodId: $paymentMethodId,
            subPaymentMethodId: $subPaymentMethodId
        );

        return new PaymentCustomerSubMethodsRouteResponse($paymentSubMethod);
    }

    private function resolveFromSession(string $paymentMethodId): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->hasSession()) {
            return null;
        }

        return $this->requestStack->getSession()->get(self::SESSION_KEY_PREFIX . $paymentMethodId);
    }

    private function resolveFromCustomer(string $paymentMethodId, SalesChannelContext $context): ?string
    {
        // Guests persist their choice on the account too (see the context switch
        // subscriber), so pre-selection must read it back for them as well.
        $customerEntity = $context->getCustomer();

        if ($customerEntity === null) {
            return null;
        }

        $customer = $this->customerRepositoryPort->getFromCustomerEntity(customerEntity: $customerEntity);
        $subMethod = $this->customerPaymentSubMethodService->getSubMethod(
            customer: $customer,
            paymentMethodId: $paymentMethodId
        );

        return $subMethod?->subPaymentMethodId;
    }
}
