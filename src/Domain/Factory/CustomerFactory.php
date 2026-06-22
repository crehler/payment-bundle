<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Factory;

use Crehler\PaymentBundle\Domain\Entity\Customer;
use Shopware\Core\Checkout\Customer\CustomerEntity;

final readonly class CustomerFactory
{
    public function create(CustomerEntity $customerEntity): Customer
    {
        return new Customer(
            id: $customerEntity->getId(),
            customerNumber: $customerEntity->getCustomerNumber(),
            email: $customerEntity->getEmail(),
            firstName: $customerEntity->getFirstName(),
            lastName: $customerEntity->getLastName(),
            phone: $customerEntity->getDefaultBillingAddress()?->getPhoneNumber(),
            isGuest: $customerEntity->getGuest(),
            customFields: $customerEntity->getCustomFields() ?? [],
        );
    }
}
