<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\PaymentSubMethods;

use Crehler\PaymentBundle\Domain\Contract\PaymentSubMethodServicePort;
use Crehler\PaymentBundle\Domain\Entity\PaymentMethod;
use Crehler\PaymentBundle\Infrastructure\StoreApi\PaymentSubMethods\Abstract\AbstractPaymentSubMethodRoute;
use Crehler\PaymentBundle\Infrastructure\Struct\PaymentSubMethod\{PaymentMethodCollection, PaymentSubMethodStruct};
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\{Autoconfigure, AutoconfigureTag};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Autoconfigure(public: true)]
#[AutoconfigureTag('controller.service_arguments')]
final class PaymentSubMethodRoute extends AbstractPaymentSubMethodRoute
{
    public function __construct(
        private PaymentSubMethodServicePort $paymentSubMethodPort,
    ) {
    }

    public function getDecorated(): AbstractPaymentSubMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/cr/payment-sub-methods/{paymentId}',
        name: 'store-api.cr.payment-sub-methods.get',
        methods: ['GET']
    )]
    public function get(string $paymentId, Request $request, SalesChannelContext $context): PaymentSubMethodRouteResponse
    {
        $paymentValue = $this->requirePaymentValue($request);

        $paymentMethod = $this->paymentSubMethodPort->getPayment(
            paymentId: $paymentId,
            paymentValue: $paymentValue,
            context: $context
        );

        return new PaymentSubMethodRouteResponse($this->mapToCollection(paymentMethod: $paymentMethod));
    }

    #[Route(
        path: '/store-api/cr/payment-sub-methods',
        name: 'store-api.cr.payment-sub-methods.current',
        methods: ['GET']
    )]
    public function getCurrent(Request $request, SalesChannelContext $context): PaymentSubMethodRouteResponse
    {
        $paymentId = $context->getPaymentMethod()->getId();
        $paymentValue = $this->requirePaymentValue($request);

        return $this->getForPayment(
            paymentId: $paymentId,
            paymentValue: $paymentValue,
            context: $context
        );
    }

    /**
     * Internal method for subscriber/service use - accepts paymentValue directly.
     */
    public function getForPayment(string $paymentId, int $paymentValue, SalesChannelContext $context): PaymentSubMethodRouteResponse
    {
        $paymentMethod = $this->paymentSubMethodPort->getPayment(
            paymentId: $paymentId,
            paymentValue: $paymentValue,
            context: $context
        );

        return new PaymentSubMethodRouteResponse($this->mapToCollection(paymentMethod: $paymentMethod));
    }

    /**
     * The amount used to fall back to a hardcoded 100 PLN when the query parameter was
     * missing. Channels are filtered by the checkout value, so that default quietly
     * misreported availability: ING serves instalments only from 300 PLN, so a caller who
     * forgot the parameter was told instalments do not exist, whatever the real cart held.
     *
     * A missing amount is a bad request now. Callers inside the shop pass the cart total
     * straight to getForPayment() and never touch the query string.
     */
    private function requirePaymentValue(Request $request): int
    {
        if (!$request->query->has('paymentValue')) {
            throw RoutingException::missingRequestParameter('paymentValue');
        }

        $paymentValue = $request->query->getInt('paymentValue');

        // Symfony's InputBag already rejects anything that is not a whole number, so the
        // one value that still gets through and should not is a negative one. It is not
        // inert: channels are filtered with $paymentValue < $raw->minAmount, so -1 reports
        // every channel that has a lower bound as unavailable, which reads to the caller
        // exactly like a gateway with nothing on offer.
        if ($paymentValue < 0) {
            throw RoutingException::invalidRequestParameter('paymentValue');
        }

        return $paymentValue;
    }

    private function mapToCollection(PaymentMethod $paymentMethod): PaymentMethodCollection
    {
        $collection = new PaymentMethodCollection();

        foreach ($paymentMethod->paymentSubMethods as $paymentSubMethod) {
            $collection->add(new PaymentSubMethodStruct(
                name: $paymentSubMethod->name,
                providerId: $paymentSubMethod->providerId,
                shopwareId: $paymentSubMethod->shopwareId,
                mediaUrl: $paymentSubMethod->mediaUrl,
            ));
        }

        return $collection;
    }
}
