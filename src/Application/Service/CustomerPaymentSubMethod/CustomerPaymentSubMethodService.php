<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service\CustomerPaymentSubMethod;

use Crehler\PaymentBundle\Application\Port\Driven\CustomerPaymentSubMethodRepositoryPort;
use Crehler\PaymentBundle\Domain\Entity\Customer;
use Crehler\PaymentBundle\Domain\Entity\CustomerPaymentSubMethod\CustomerPaymentSubMethod;
use Crehler\PaymentBundle\Domain\Exception\PaymentMethodNotFoundException;
use Shopware\Core\Framework\Context;

final readonly class CustomerPaymentSubMethodService
{
    public function __construct(
        private CustomerPaymentSubMethodRepositoryPort $customerPaymentSubMethodRepository,
    ) {
    }

    public function getSubMethod(Customer $customer, string $paymentMethodId): ?CustomerPaymentSubMethod
    {
        return $this->customerPaymentSubMethodRepository->get(customer: $customer, paymentMethodId: $paymentMethodId);
    }

    /**
     * @throws PaymentMethodNotFoundException
     */
    public function setSubMethod(Customer $customer, CustomerPaymentSubMethod $customerPaymentSubMethod, Context $context): void
    {
        $paymentMethodExists = $this->customerPaymentSubMethodRepository->isPaymentMethodExists(
            paymentMethodId: $customerPaymentSubMethod->paymentMethodId,
            context: $context
        );

        if (!$paymentMethodExists) {
            throw new PaymentMethodNotFoundException();
        }

        $this->customerPaymentSubMethodRepository->save(
            customer: $customer,
            customerPaymentSubMethod: $customerPaymentSubMethod,
            context: $context
        );
    }
}
