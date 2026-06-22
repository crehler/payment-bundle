<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Repository\Customer;

use Crehler\PaymentBundle\Application\Port\Driven\CustomerPaymentSubMethodRepositoryPort;
use Crehler\PaymentBundle\Domain\Entity\Customer;
use Crehler\PaymentBundle\Domain\Entity\CustomerPaymentSubMethod\CustomerPaymentSubMethod as CustomerPaymentSubMethodEntity;
use Crehler\PaymentBundle\Infrastructure\Enum\{CustomFieldsEnum, PaymentMethods};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

use function array_key_exists;
use function is_array;

final class CustomerPaymentSubMethodRepository implements CustomerPaymentSubMethodRepositoryPort
{
    /**
     * @var string
     */
    public const PAYMENT_METHOD = 'payment_method';

    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $paymentMethodRepository,
    ) {
    }

    public function get(Customer $customer, string $paymentMethodId): ?CustomerPaymentSubMethodEntity
    {
        if (!array_key_exists(PaymentMethods::PAYMENT_CUSTOM_FIELD->value, $customer->customFields)) {
            return null;
        }

        $customField = $customer->customFields[PaymentMethods::PAYMENT_CUSTOM_FIELD->value];

        if (!array_key_exists(PaymentMethods::PAYMENT_METHOD->value . $paymentMethodId, $customField)) {
            return null;
        }

        $subMethodId = $customField[PaymentMethods::PAYMENT_METHOD->value . $paymentMethodId];

        return new CustomerPaymentSubMethodEntity(paymentMethodId: $paymentMethodId, subPaymentMethodId: $subMethodId);
    }

    public function save(Customer $customer, CustomerPaymentSubMethodEntity $customerPaymentSubMethod, Context $context): void
    {
        $existingCustomFields = $customer->customFields ?? [];
        $field = CustomFieldsEnum::CUSTOM_FIELD_CUSTOMER_PAYMENT->value;

        $crehlerPayments = [];
        if (isset($existingCustomFields[$field]) && is_array($existingCustomFields[$field])) {
            $crehlerPayments = $existingCustomFields[$field];
        }
        $crehlerPayments[$this->getPaymentMethodKey($customerPaymentSubMethod)] = $customerPaymentSubMethod->subPaymentMethodId;

        $data = [
            'id' => $customer->id,
            'customFields' => [
                'crehler_payments' => $crehlerPayments,
            ],
        ];

        $this->customerRepository->upsert([$data], $context);
    }

    public function isPaymentMethodExists(string $paymentMethodId, Context $context): bool
    {
        $criteria = new Criteria([$paymentMethodId]);

        return $this->paymentMethodRepository->search(criteria: $criteria, context: $context)->count() > 0;
    }

    private function getPaymentMethodKey(CustomerPaymentSubMethodEntity $method): string
    {
        return PaymentMethods::PAYMENT_METHOD->value . $method->paymentMethodId;
    }
}
