<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Contract;

use Crehler\PaymentBundle\Domain\Entity\Customer;
use Crehler\PaymentBundle\Domain\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

interface CustomerRepositoryPort
{
    /**
     * @throws CustomerNotFoundException
     */
    public function getCustomerById(string $customerId, Context $context): ?Customer;

    public function getFromCustomerEntity(CustomerEntity $customerEntity): Customer;
}
