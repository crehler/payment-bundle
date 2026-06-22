<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Domain\Contract\{PaymentSubMethodPort, PaymentSubMethodServicePort};
use Crehler\PaymentBundle\Domain\Entity\PaymentMethod;
use Crehler\PaymentBundle\Domain\Repository\PaymentMethodRepositoryInterface;
use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class PaymentSubMethodService implements PaymentSubMethodServicePort
{
    public function __construct(
        private PaymentSubMethodPort $paymentSubMethodPort,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function getPayment(string $paymentId, int $paymentValue, SalesChannelContext $context): PaymentMethod
    {
        $paymentMethod = new PaymentMethod(paymentSubMethods: []);

        $paymentMethodEntity = $this->paymentMethodRepository->findById(paymentMethodId: $paymentId, context: $context);

        if (!$paymentMethodEntity) {
            return $paymentMethod;
        }

        $paymentCollectionArr = $this->paymentSubMethodPort->getPaymentSubMethods(
            paymentMethodEntity: $paymentMethodEntity,
            paymentValue: $paymentValue,
            context: $context
        );
        /** @var array<PaymentSubMethod> $paymentCollectionArr */
        foreach ($paymentCollectionArr as $paymentSubMethod) {
            $paymentMethod = $paymentMethod->addPaymentSubMethod(paymentSubMethod: $paymentSubMethod);
        }

        return $paymentMethod;
    }
}
