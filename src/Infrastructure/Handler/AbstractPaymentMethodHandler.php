<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Handler;

use Crehler\PaymentBundle\Application\DTO\Refund\{RefundCommand, RefundPositionCommand};
use Crehler\PaymentBundle\Application\Port\Driven\{OrderTransactionRepositoryInterface, PaymentSubMethodSessionResolverPort, RefundProviderPort};
use Crehler\PaymentBundle\Application\Port\Driving\OrderTransactionServicePort;
use Crehler\PaymentBundle\Application\Service\RefundStateReflector;
use Crehler\PaymentBundle\Domain\Constant\PaymentCustomFields;
use Crehler\PaymentBundle\Domain\Entity\OrderTransaction\OrderTransaction;
use Crehler\PaymentBundle\Domain\ValueObjects\{RefundOrigin, RefundStatus};
use Crehler\PaymentBundle\Infrastructure\Configuration\PaymentBundleConfigService;
use Crehler\PaymentBundle\Shared\{EnhancedLogger, FinalizeTokenService, UrlSigner};
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionEntity, OrderTransactionStateHandler};
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\{OrderTransactionCaptureRefundEntity, OrderTransactionCaptureRefundStateHandler};
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\{AbstractPaymentHandler, PaymentHandlerType};
use Shopware\Core\Checkout\Payment\Cart\{PaymentTransactionStruct, RefundPaymentTransactionStruct};
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\{Autowire, AutowireIterator};
use Symfony\Component\HttpFoundation\{RedirectResponse, Request};
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

use function in_array;
use function is_array;
use function is_string;
use function parse_str;
use function parse_url;
use function round;
use function sprintf;

/**
 * Abstract base class for payment method handlers.
 *
 * Provides common functionality for all payment handlers:
 * - Order transaction retrieval
 * - Payment sub-method resolution
 * - Exception handling with logging
 * - Redirect response generation
 *
 * Subclasses must implement:
 * - getPaymentProviderName(): Identifier for logging
 * - getTransitionRouteName(): Route for payment transition page
 * - processPayment(): Provider-specific payment logic
 */
abstract class AbstractPaymentMethodHandler extends AbstractPaymentHandler
{
    /**
     * Custom-field key under which the admin refund modal stores the operator-selected
     * predefined reason code (from a provider's RefundReasonProviderInterface). Kept in
     * sync with the administration component cr-payment-refund-card.
     */
    public const REFUND_REASON_CODE_FIELD = 'crehler_payment_refund_reason_code';

    /**
     * Refund collaborators are injected via a setter (not the constructor) so the
     * provider handler subclasses — which redefine the constructor and forward
     * positionally — don't need to know about them. Symfony calls #[Required]
     * setters on the concrete service regardless of its constructor signature.
     *
     * @var iterable<RefundProviderPort>
     */
    protected iterable $refundProviders = [];

    protected ?OrderTransactionCaptureRefundStateHandler $refundStateHandler = null;

    protected ?OrderTransactionStateHandler $transactionStateHandler = null;

    protected ?EntityRepository $refundRepository = null;

    protected ?RefundStateReflector $refundStateReflector = null;

    /**
     * Injected via setter (not the constructor) for the same reason as the refund
     * collaborators: provider subclasses redefine the constructor and forward
     * positionally, so a new constructor argument would break them all.
     */
    protected ?UrlSigner $urlSigner = null;

    /**
     * Injected via setter for the same reason as urlSigner above.
     */
    protected ?PaymentBundleConfigService $paymentBundleConfigService = null;

    private bool $refundProviderResolved = false;

    private ?RefundProviderPort $resolvedRefundProvider = null;

    public function __construct(
        protected readonly EnhancedLogger $logger,
        protected readonly RouterInterface $router,
        protected readonly OrderTransactionServicePort $orderTransactionServicePort,
        protected readonly FinalizeTokenService $finalizeTokenService,
        protected readonly PaymentSubMethodSessionResolverPort $paymentSubMethodSessionResolver,
        protected readonly OrderTransactionRepositoryInterface $orderTransactionRepository,
    ) {
    }

    /**
     * @param iterable<RefundProviderPort> $refundProviders
     */
    #[Required]
    public function setRefundCollaborators(
        #[AutowireIterator(RefundProviderPort::class)]
        iterable $refundProviders,
        OrderTransactionCaptureRefundStateHandler $refundStateHandler,
        OrderTransactionStateHandler $transactionStateHandler,
        #[Autowire(service: 'order_transaction_capture_refund.repository')]
        EntityRepository $refundRepository,
        RefundStateReflector $refundStateReflector,
    ): void {
        $this->refundProviders = $refundProviders;
        $this->refundStateHandler = $refundStateHandler;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->refundRepository = $refundRepository;
        $this->refundStateReflector = $refundStateReflector;
    }

    #[Required]
    public function setUrlSigner(UrlSigner $urlSigner): void
    {
        $this->urlSigner = $urlSigner;
    }

    #[Required]
    public function setPaymentBundleConfigService(PaymentBundleConfigService $paymentBundleConfigService): void
    {
        $this->paymentBundleConfigService = $paymentBundleConfigService;
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        if ($type === PaymentHandlerType::REFUND) {
            return $this->resolveRefundProvider() !== null;
        }

        return false;
    }

    /**
     * Native Shopware refund entry point (called by PaymentRefundProcessor when
     * supports(REFUND) is true). Loads the refund aggregate, delegates the gateway
     * call to the provider's RefundProviderPort, then drives both state machines:
     * the refund's (complete/process) and — unlike the core processor — the order
     * transaction's (refund/refundPartially) so the order reflects "refunded".
     */
    public function refund(RefundPaymentTransactionStruct $transaction, Context $context): void
    {
        $provider = $this->resolveRefundProvider();
        if ($provider === null) {
            throw PaymentException::paymentHandlerTypeUnsupported($this, PaymentHandlerType::REFUND);
        }

        // Fail closed rather than fail open: a DI misconfiguration that left the refund
        // collaborators unset must abort here, not silently skip the state transitions
        // (which would refund at the gateway while the order still shows "paid").
        if ($this->refundStateHandler === null
            || $this->transactionStateHandler === null
            || $this->refundRepository === null
            || $this->refundStateReflector === null
        ) {
            throw PaymentException::refundInterrupted($transaction->getRefundId(), 'Refund collaborators are not initialized (DI misconfiguration).');
        }

        $refund = $this->loadRefund($transaction->getRefundId(), $context);
        $capture = $refund->getTransactionCapture();
        $orderTransaction = $capture?->getTransaction();
        $order = $orderTransaction?->getOrder();

        if ($capture === null || $orderTransaction === null || $order === null) {
            throw PaymentException::unknownRefund($transaction->getRefundId());
        }

        // Marks who initiated the refund (not the outcome) — set unconditionally before
        // the gateway call below, which may still fail.
        $this->refundRepository->update([[
            'id' => $refund->getId(),
            'customFields' => [PaymentCustomFields::REFUND_ORIGIN => RefundOrigin::SHOP->value],
        ]], $context);

        $gatewayPaymentId = $this->resolveGatewayPaymentId($capture, $orderTransaction);
        if ($gatewayPaymentId === null || $gatewayPaymentId === '') {
            throw PaymentException::refundInterrupted($transaction->getRefundId(), 'Missing gateway payment id on capture and transaction');
        }

        // The operator-selected predefined reason (if any) is stored on the refund entity's
        // custom fields by the admin modal; the free-text reason stays in $refund->getReason().
        $reasonCode = $refund->getCustomFields()[self::REFUND_REASON_CODE_FIELD] ?? null;

        $command = new RefundCommand(
            orderTransactionId: $orderTransaction->getId(),
            gatewayPaymentId: $gatewayPaymentId,
            amount: $this->toMinorUnits($refund->getAmount()->getTotalPrice()),
            currencyIso: $order->getCurrency()?->getIsoCode() ?? '',
            reason: $refund->getReason(),
            positions: $this->buildPositions($refund),
            refundId: $refund->getId(),
            reasonCode: is_string($reasonCode) ? $reasonCode : null,
        );

        $result = $provider->refund($command, $context);

        if ($result->isFailed()) {
            // Rethrow so the native processor transitions the refund to "failed".
            throw PaymentException::refundInterrupted($transaction->getRefundId(), $result->message ?? 'Refund rejected by the payment gateway');
        }

        if ($result->gatewayRefundId !== null && $result->gatewayRefundId !== '') {
            $this->refundRepository?->update([[
                'id' => $refund->getId(),
                'externalReference' => $result->gatewayRefundId,
            ]], $context);
        }

        match ($result->status) {
            RefundStatus::COMPLETED => $this->refundStateHandler?->complete($refund->getId(), $context),
            RefundStatus::IN_PROGRESS => $this->refundStateHandler?->process($refund->getId(), $context),
            RefundStatus::FAILED => null,
        };

        // The refund's own state machine has just been transitioned above; reflect it onto
        // the order transaction. A gateway-accepted refund (in_progress) already flips the
        // payment to refunded / refunded_partially — failures threw earlier and never reach here.
        $this->refundStateReflector?->reflect($orderTransaction->getId(), $capture->getId(), $context);
    }

    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct,
    ): ?RedirectResponse {
        try {
            $orderTransaction = $this->orderTransactionServicePort->getOrderTransaction(
                orderTransactionId: $transaction->getOrderTransactionId(),
                context: $context
            );

            $paymentSubMethodId = $this->resolvePaymentSubMethod(
                request: $request,
                orderTransaction: $orderTransaction
            );

            $paymentResult = $this->processPayment(
                request: $request,
                transaction: $transaction,
                orderTransaction: $orderTransaction,
                paymentSubMethodId: $paymentSubMethodId,
                context: $context
            );

            // Provider rejected the payment (declined / error) → fail the transaction with a
            // meaningful message instead of building a RedirectResponse from an empty URL.
            if (!$paymentResult->isSuccess()) {
                throw PaymentException::asyncProcessInterrupted($transaction->getOrderTransactionId(), $paymentResult->errorMessage ?? 'Payment was rejected by the provider.');
            }

            // Payment initiated at the gateway → mark the transaction "in progress"; the
            // gateway notification then drives the final state (operator panel is the truth).
            $this->markInProgress($transaction->getOrderTransactionId(), $context);

            // Immediate success without a redirect (e.g. card accepted directly, no 3DS):
            // there is no gateway URL to send the browser to. Return null — the transaction
            // stays "in progress" and the gateway webhook transitions it to "paid".
            if ($paymentResult->redirectUrl === '') {
                return null;
            }

            return $this->createRedirectResponse(
                request: $request,
                orderId: $orderTransaction->order->id,
                redirectUrl: $paymentResult->redirectUrl
            );
        } catch (Throwable $e) {
            $this->logger->critical(
                sprintf('Exception in %s payment process', $this->getPaymentProviderName()),
                [
                    'exception' => $e,
                    'orderTransactionId' => $transaction->getOrderTransactionId(),
                ]
            );

            throw $e;
        }
    }

    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        // Intentional no-op: the final transaction state is driven by the gateway
        // webhook (POST /payment/notification), not by the browser return. Nothing
        // to do here — and never log the _sw_payment_token / return URL (payment secret).
    }

    /**
     * Shared BLIK pay() flow for handlers that support the layer-zero BLIK code.
     *
     * When the customer submits a BLIK code in the shop, the payment is authorized
     * in-place and the browser is sent to the BLIK authorize page (which polls the
     * payment status). Without a code, the customer is redirected to the gateway like
     * any other payment method.
     *
     * Provider BLIK handlers override pay() with a one-line delegation to this method
     * and implement only processPayment().
     */
    protected function payViaBlikAuthorize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
    ): ?RedirectResponse {
        try {
            $orderTransaction = $this->orderTransactionServicePort->getOrderTransaction(
                orderTransactionId: $transaction->getOrderTransactionId(),
                context: $context
            );

            $blikCode = $request->get('blikCode');

            // 'separate' input position: the code field is never rendered in checkout, so
            // there is nothing to authorize yet. Send the customer straight to the bundle's
            // own code-entry page instead of calling the gateway here — the gateway
            // transaction is created once, when the code is actually submitted from that
            // page (via the existing BLIK retry endpoint), so no codeless/never-confirmed
            // transaction is left dangling at the provider.
            if (empty($blikCode) && $this->resolveBlikInputPosition($orderTransaction) === 'separate') {
                $this->markInProgress($transaction->getOrderTransactionId(), $context);

                return new RedirectResponse(
                    $this->router->generate(
                        name: 'frontend.cr.blik.authorize',
                        parameters: [
                            'transactionId' => $transaction->getOrderTransactionId(),
                            'target' => $transaction->getReturnUrl(),
                        ],
                        referenceType: RouterInterface::ABSOLUTE_URL
                    )
                );
            }

            $paymentResult = $this->processPayment(
                request: $request,
                transaction: $transaction,
                orderTransaction: $orderTransaction,
                paymentSubMethodId: null,
                context: $context
            );

            // Payment initiated (BLIK code authorized / redirect to gateway) → mark the
            // transaction "in progress"; the gateway notification drives the final state.
            $this->markInProgress($transaction->getOrderTransactionId(), $context);

            // BLIK code provided -> already authorized at the gateway above; the authorize
            // page must skip the code-entry phase and poll directly (authorized=1). Without
            // this flag the page re-prompts and a second submit double-authorizes the order.
            if (!empty($blikCode)) {
                return new RedirectResponse(
                    $this->router->generate(
                        name: 'frontend.cr.blik.authorize',
                        parameters: [
                            'transactionId' => $transaction->getOrderTransactionId(),
                            'target' => $transaction->getReturnUrl(),
                            'authorized' => 1,
                        ],
                        referenceType: RouterInterface::ABSOLUTE_URL
                    )
                );
            }

            // No BLIK code -> redirect to the gateway (via transition page on Storefront).
            return $this->createRedirectResponse(
                request: $request,
                orderId: $orderTransaction->order->id,
                redirectUrl: $paymentResult->redirectUrl
            );
        } catch (Throwable $e) {
            $this->logger->critical(
                sprintf('Exception in %s payment process', $this->getPaymentProviderName()),
                [
                    'exception' => $e,
                    'orderTransactionId' => $transaction->getOrderTransactionId(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Returns the payment provider name for logging purposes.
     */
    abstract protected function getPaymentProviderName(): string;

    /**
     * Returns the route name for the payment transition page.
     * Default implementation returns the unified bundle route.
     */
    protected function getTransitionRouteName(): string
    {
        return 'frontend.cr.payment.transition';
    }

    /**
     * Returns the provider logo identifier for the transition page.
     * Override in subclass to specify provider-specific logo (e.g., 'paynow', 'payu').
     */
    protected function getProviderLogo(): string
    {
        return 'default';
    }

    /**
     * Process the payment with the specific payment provider.
     *
     * @return PaymentResult The result containing redirect URL
     */
    abstract protected function processPayment(
        Request $request,
        PaymentTransactionStruct $transaction,
        OrderTransaction $orderTransaction,
        ?string $paymentSubMethodId,
        Context $context,
    ): PaymentResult;

    protected function resolvePaymentSubMethod(Request $request, OrderTransaction $orderTransaction): ?string
    {
        return $this->paymentSubMethodSessionResolver->resolve(
            request: $request,
            paymentMethodId: $orderTransaction->paymentMethod->id,
            customer: $orderTransaction->order->customer
        );
    }

    protected function createRedirectResponse(Request $request, string $orderId, string $redirectUrl): RedirectResponse
    {
        // For Store API (headless) - return direct provider URL
        if ($this->isStoreApiRequest($request)) {
            return new RedirectResponse($redirectUrl);
        }

        if ($this->urlSigner === null) {
            // Fail closed: without the signer we cannot produce a tamper-proof target,
            // and the transition page rejects unsigned targets (open-redirect guard).
            throw new RuntimeException('UrlSigner is not initialized (DI misconfiguration).');
        }

        // For Storefront - use transition page for better UX. The gateway target is
        // HMAC-signed so the transition page can reject a client-swapped ?target=...
        // (open-redirect / phishing). See PaymentTransitionController.
        $parameters = [
            'orderId' => $orderId,
            'target' => $redirectUrl,
            'sig' => $this->urlSigner->sign($redirectUrl),
            'logo' => $this->getProviderLogo(),
        ];

        $transitionUrl = $this->router->generate(
            name: $this->getTransitionRouteName(),
            parameters: $parameters,
            referenceType: RouterInterface::ABSOLUTE_URL
        );

        return new RedirectResponse($transitionUrl);
    }

    /**
     * Checks if the current request is from Store API (headless).
     */
    protected function isStoreApiRequest(Request $request): bool
    {
        $routeScope = $request->attributes->get('_routeScope', []);

        if (is_array($routeScope) && in_array('store-api', $routeScope, true)) {
            return true;
        }

        // Fallback: check for sw-access-key header (Store API identifier)
        return $request->headers->has('sw-access-key');
    }

    protected function buildNotifyUrl(OrderTransaction $orderTransaction): string
    {
        return $this->finalizeTokenService->buildUrl(orderTransaction: $orderTransaction);
    }

    /**
     * Build the gateway notification URL and the browser return URL in one call.
     * Removes the boilerplate every processPayment() repeats.
     *
     * @return array{notifyUrl: string, returnUrl: string}
     */
    protected function buildPaymentUrls(
        OrderTransaction $orderTransaction,
        ?PaymentTransactionStruct $transaction = null,
    ): array {
        return [
            'notifyUrl' => $this->buildNotifyUrl($orderTransaction),
            'returnUrl' => $this->buildBrowserReturnUrl($orderTransaction, $transaction),
        ];
    }

    /**
     * Persist the gateway payment id on the order transaction. No-op when the
     * gateway returned no id, so callers don't need to null-check.
     */
    protected function persistGatewayPaymentId(
        string $orderTransactionId,
        ?string $gatewayPaymentId,
        Context $context,
    ): void {
        if ($gatewayPaymentId === null || $gatewayPaymentId === '') {
            return;
        }

        $this->orderTransactionRepository->updateGatewayPaymentId(
            orderTransactionId: $orderTransactionId,
            gatewayPaymentId: $gatewayPaymentId,
            context: $context,
        );
    }

    /**
     * Short, HMAC-signed browser return URL for the payment gateway.
     * See PaymentReturnController for the why: long Shopware return URLs
     * with embedded JWT get truncated at ~512 chars by Tpay sandbox.
     *
     * Pass $transaction so the caller-supplied finishUrl/errorUrl (encoded by
     * Shopware in the JWT inside getReturnUrl()) are persisted on the
     * transaction — otherwise the short URL drops them and the post-payment
     * redirect falls back to a generic checkout/finish URL regardless of what
     * the storefront/Store API caller requested.
     */
    protected function buildBrowserReturnUrl(
        OrderTransaction $orderTransaction,
        ?PaymentTransactionStruct $transaction = null,
    ): string {
        [$finishUrl, $errorUrl] = $transaction !== null
            ? $this->extractCallerUrlsFromTransaction($transaction)
            : [null, null];

        return $this->finalizeTokenService->buildBrowserReturnUrl(
            orderTransaction: $orderTransaction,
            finishUrl: $finishUrl,
            errorUrl: $errorUrl,
        );
    }

    /**
     * @return string One of: 'checkout', 'separate', 'hidden'
     */
    private function resolveBlikInputPosition(OrderTransaction $orderTransaction): string
    {
        return $this->paymentBundleConfigService?->getBlikInputPositionForHandler(
            handlerIdentifier: $orderTransaction->paymentMethod->handlerIdentifier,
            salesChannelId: $orderTransaction->order->salesChannelId,
        ) ?? 'checkout';
    }

    /**
     * Mark the transaction "in progress" the moment the payment is initiated (redirected to
     * the gateway / BLIK code authorized). The gateway notification then drives the final
     * state (paid/failed/cancelled) — the operator panel is the source of truth. A payment
     * the gateway never resolves stays "in progress" on purpose; the merchant can act on it
     * (e.g. via Shopware Commercial delayed actions). Idempotent: a no-op when the state has
     * already advanced (e.g. a fast webhook beating this transition).
     */
    private function markInProgress(string $orderTransactionId, Context $context): void
    {
        try {
            $this->transactionStateHandler?->process($orderTransactionId, $context);
        } catch (IllegalTransitionException $e) {
            $this->logger->info('Skipping in_progress transition (state already advanced)', [
                'orderTransactionId' => $orderTransactionId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string} [finishUrl, errorUrl]
     */
    private function extractCallerUrlsFromTransaction(PaymentTransactionStruct $transaction): array
    {
        $token = $this->extractTokenFromUrl($transaction->getReturnUrl());
        if ($token === '') {
            return [null, null];
        }

        try {
            $tokenStruct = $this->finalizeTokenService->parseToken($token);
        } catch (Throwable) {
            return [null, null];
        }

        return [$tokenStruct->getFinishUrl(), $tokenStruct->getErrorUrl()];
    }

    private function extractTokenFromUrl(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query === null || $query === false) {
            return '';
        }
        parse_str($query, $params);

        return $params['_sw_payment_token'] ?? '';
    }

    /**
     * Find the RefundProviderPort that serves this handler (matched by the concrete
     * handler class, which equals the payment method's handler identifier). Memoized.
     */
    private function resolveRefundProvider(): ?RefundProviderPort
    {
        if ($this->refundProviderResolved) {
            return $this->resolvedRefundProvider;
        }

        $this->refundProviderResolved = true;

        foreach ($this->refundProviders as $provider) {
            if ($provider->supports(static::class)) {
                $this->resolvedRefundProvider = $provider;
                break;
            }
        }

        return $this->resolvedRefundProvider;
    }

    private function loadRefund(string $refundId, Context $context): OrderTransactionCaptureRefundEntity
    {
        $criteria = new Criteria([$refundId]);
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('transactionCapture.transaction.order.orderCustomer');
        $criteria->addAssociation('transactionCapture.transaction.order.currency');
        $criteria->addAssociation('transactionCapture.refunds.stateMachineState');
        $criteria->addAssociation('positions.orderLineItem');

        $refund = $this->refundRepository?->search($criteria, $context)->getEntities()->first();

        if (!$refund instanceof OrderTransactionCaptureRefundEntity) {
            throw PaymentException::unknownRefund($refundId);
        }

        return $refund;
    }

    private function resolveGatewayPaymentId(
        OrderTransactionCaptureEntity $capture,
        OrderTransactionEntity $orderTransaction,
    ): ?string {
        $fromCapture = $capture->getExternalReference();
        if ($fromCapture !== null && $fromCapture !== '') {
            return $fromCapture;
        }

        $customFields = $orderTransaction->getCustomFields() ?? [];

        return $customFields[PaymentCustomFields::GATEWAY_PAYMENT_ID] ?? null;
    }

    /**
     * @return RefundPositionCommand[]
     */
    private function buildPositions(OrderTransactionCaptureRefundEntity $refund): array
    {
        $positions = [];

        foreach ($refund->getPositions() ?? [] as $position) {
            $positions[] = new RefundPositionCommand(
                orderLineItemId: $position->getOrderLineItemId(),
                quantity: $position->getQuantity(),
                amount: $this->toMinorUnits($position->getAmount()->getTotalPrice()),
            );
        }

        return $positions;
    }

    private function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
