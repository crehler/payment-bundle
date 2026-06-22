<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Domain\Entity\Customer;
use Crehler\PaymentBundle\Domain\Entity\CustomerPaymentSubMethod\CustomerPaymentSubMethod;
use Shopware\Core\Framework\Context;

interface CustomerPaymentSubMethodRepositoryPort
{
    public function get(Customer $customer, string $paymentMethodId): ?CustomerPaymentSubMethod;

    public function save(Customer $customer, CustomerPaymentSubMethod $customerPaymentSubMethod, Context $context): void;

    public function isPaymentMethodExists(string $paymentMethodId, Context $context): bool;
}
