<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Storefront\PaymentSubMethods;

use Crehler\PaymentBundle\Domain\Contract\PaymentSubMethodServicePort;
use Crehler\PaymentBundle\Domain\Repository\PaymentMethodRepositoryInterface;
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentMethodContractResolver;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSubMethods\Abstract\AbstractCustomerPaymentSubMethodRoute;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSubMethods\CustomerPaymentSubMethodRoute;
use Crehler\PaymentBundle\Shared\AmountFormat;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannel\{AbstractContextSwitchRoute, ContextSwitchRoute};
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use function is_string;
use function preg_match;

/**
 * Renders the channel widget for one payment method, off the checkout's critical path.
 *
 * A mirror of the Store API route in purpose, deliberately not a replacement for it:
 * headless storefronts keep consuming /store-api/cr/payment-sub-methods and its JSON
 * contract is untouched. This one returns HTML because the widget has three server-side
 * branches (no channels, one auto-selected with a hidden input, a chooser) and handing
 * JSON to the browser would mean reimplementing all three in JavaScript — a second
 * renderer to keep in step with the first.
 *
 * The amount comes from the cart on the server — or, on the edit-order page, from the
 * order. It is never accepted from the query string: channels are filtered by it, so a
 * caller-supplied amount would let the caller decide which channels the customer is told
 * exist. The Store API route has to take it as a parameter because a headless client owns
 * its own cart; nothing in the storefront needs that freedom.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
final class PaymentSubMethodsController extends StorefrontController
{
    public function __construct(
        private readonly PaymentSubMethodServicePort $paymentSubMethodService,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentMethodContractResolver $contractResolver,
        #[Autowire(service: CustomerPaymentSubMethodRoute::class)]
        private readonly AbstractCustomerPaymentSubMethodRoute $customerSubMethodRoute,
        private readonly CartService $cartService,
        private readonly AmountFormat $amountFormat,
        private readonly EntityRepository $orderRepository,
        // Shopware does not alias the abstract, so the concrete route is named explicitly.
        #[Autowire(service: ContextSwitchRoute::class)]
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
    ) {
    }

    #[Route(
        path: '/cr/payment/sub-methods/{paymentMethodId}',
        name: 'frontend.cr.payment.sub-methods',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        requirements: ['paymentMethodId' => '[0-9a-fA-F]{32}'],
        methods: ['GET'],
    )]
    public function subMethods(string $paymentMethodId, Request $request, SalesChannelContext $context): Response
    {
        $paymentMethod = $this->paymentMethodRepository->findById($paymentMethodId, $context);

        if ($paymentMethod === null) {
            throw new NotFoundHttpException();
        }

        $contract = $this->contractResolver->resolve($paymentMethod);

        // Only a method whose handler declares that its channels come from the gateway has
        // a widget at all. Anything else — BLIK, a card, a third-party method — gets a 404
        // rather than an empty widget, so a wrong URL fails loudly in the console instead
        // of rendering nothing and looking like an outage.
        if ($contract === null || !$contract->usesGatewayChannels) {
            throw new NotFoundHttpException();
        }

        $subMethods = $this->paymentSubMethodService->getPayment(
            paymentId: $paymentMethodId,
            paymentValue: $this->paymentValue($request->query->get('orderId'), $context),
            context: $context,
        )->paymentSubMethods;

        return $this->renderStorefront('@CrehlerPaymentBundle/storefront/component/payment/payment-sub-methods.html.twig', [
            'submethods' => $subMethods,
            'selectedSubPaymentMethodId' => $this->customerSubMethodRoute
                ->get($paymentMethod, $context)
                ->get()
                ->subPaymentMethodId,
            'payment' => $paymentMethod,
            'crPaymentContract' => $contract,
        ]);
    }

    /**
     * Set the channel and hand back the same widget, re-rendered.
     *
     * Choosing a bank used to reload the whole confirm page, because the channel radios
     * carried Shopware's .payment-method-input and FormAutoSubmitPlugin submits the form on
     * any change to one. A full round trip through the page to record one radio — while the
     * cart, the addresses, the totals and every other payment method stay exactly as they
     * were — is all cost and no benefit.
     *
     * The write still goes through the context switch, which is the single entry point for
     * setting a sub-method and the only place that validates it (see
     * SalesChannelContextSwitchSubscriber). This just wraps it so the storefront gets the
     * updated widget back in the same response instead of a whole page.
     */
    #[Route(
        path: '/cr/payment/sub-methods/{paymentMethodId}',
        name: 'frontend.cr.payment.sub-methods.select',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        requirements: ['paymentMethodId' => '[0-9a-fA-F]{32}'],
        methods: ['POST'],
    )]
    public function selectSubMethod(string $paymentMethodId, Request $request, SalesChannelContext $context): Response
    {
        // paymentSubMethodFor is stated explicitly rather than left implicit: the subscriber
        // uses it to tell a channel chosen for THIS method from one left over on a method the
        // customer already switched away from.
        $this->contextSwitchRoute->switchContext(new RequestDataBag([
            'paymentMethodId' => $paymentMethodId,
            'paymentSubMethod' => $request->request->get('paymentSubMethod'),
            'paymentSubMethodFor' => $paymentMethodId,
        ]), $context);

        // Re-render from the same place the GET does, so the selected-channel panel and the
        // chooser can never drift apart. The channel list itself is unchanged — same method,
        // same cart — so this reads straight from the cache.
        return $this->subMethods($paymentMethodId, $request, $context);
    }

    /**
     * The channel minimums are checked against this, so it has to be what the customer is
     * actually about to pay: the order on the edit-order page, the live cart in checkout.
     */
    private function paymentValue(mixed $orderId, SalesChannelContext $context): int
    {
        // Only an ABSENT orderId means "use the cart". Anything present that does not
        // resolve to one of this customer's orders is a 404, never a quiet fallback: the
        // cart is a different amount, and answering for it would report the wrong channels
        // as confidently as the right ones. Malformed ids are screened out here because
        // Criteria throws on a non-uuid rather than returning nothing.
        if (is_string($orderId) && $orderId !== '') {
            $order = preg_match('/^[0-9a-fA-F]{32}$/', $orderId) === 1
                ? $this->loadOwnOrder($orderId, $context)
                : null;

            if ($order === null) {
                throw new NotFoundHttpException();
            }

            return $this->amountFormat->floatToInt($order->getAmountTotal());
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        return $this->amountFormat->floatToInt($cart->getPrice()->getTotalPrice());
    }

    /**
     * Ownership is checked here rather than trusted from the page that rendered the widget:
     * the URL is in the customer's own DOM, so swapping the id is trivial. Guests included
     * — edit-order allows them, and their orders hang off a real customer row.
     */
    private function loadOwnOrder(string $orderId, SalesChannelContext $context): ?OrderEntity
    {
        $customer = $context->getCustomer();

        if ($customer === null) {
            return null;
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('orderCustomer.customerId', $customer->getId()));

        return $this->orderRepository->search($criteria, $context->getContext())->first();
    }
}
