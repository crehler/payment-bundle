<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\ValueResolver;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class PaymentMethodEntityValueResolver implements ValueResolverInterface
{
    public function __construct(
        #[Autowire(service: 'payment_method.repository')]
        private readonly EntityRepository $paymentMethodRepository,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== PaymentMethodEntity::class) {
            return [];
        }

        $context = $request->attributes->get('sw-sales-channel-context');

        if (!$context instanceof SalesChannelContext) {
            return [];
        }

        $paymentMethodId = $request->query->getString('paymentMethodId')
            ?: $request->request->getString('paymentMethodId');

        // If no paymentMethodId provided, use current payment method from context
        if ($paymentMethodId === '') {
            $currentPaymentMethod = $context->getPaymentMethod();
            if ($currentPaymentMethod instanceof PaymentMethodEntity) {
                yield $currentPaymentMethod;
            }

            return [];
        }

        $paymentMethod = $this->paymentMethodRepository->search(
            new Criteria([$paymentMethodId]),
            $context->getContext()
        )->first();

        if (!$paymentMethod instanceof PaymentMethodEntity) {
            return [];
        }

        yield $paymentMethod;
    }
}
