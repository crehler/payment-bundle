<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Repository\Customer;

use Crehler\PaymentBundle\Domain\Contract\CustomerRepositoryPort;
use Crehler\PaymentBundle\Domain\Entity\Customer;
use Crehler\PaymentBundle\Domain\Exception\CustomerNotFoundException;
use Crehler\PaymentBundle\Domain\Factory\CustomerFactory;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final readonly class CustomerRepository implements CustomerRepositoryPort
{
    public function __construct(
        private EntityRepository $customerRepository,
        private CustomerFactory $customerFactory,
    ) {
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function getCustomerById(string $customerId, Context $context): ?Customer
    {
        $criteria = new Criteria([$customerId]);

        $customer = $this->customerRepository->search($criteria, $context)->first();

        if (!$customer) {
            throw new CustomerNotFoundException($customerId);
        }

        return $this->customerFactory->create(customerEntity: $customer);
    }

    public function getFromCustomerEntity(CustomerEntity $customerEntity): Customer
    {
        return $this->customerFactory->create(customerEntity: $customerEntity);
    }
}
