<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service\BlikPayment;

use Crehler\PaymentBundle\Application\DTO\BlikPayment\{BlikPaymentResponseDTO, BlikRetryPaymentRequestDTO};
use Crehler\PaymentBundle\Application\Port\Driven\OrderTransactionRepositoryInterface;
use Crehler\PaymentBundle\Application\Service\Security\OrderOwnershipGuard;
use Crehler\PaymentBundle\Domain\Repository\OrderRepositoryInterface;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionEntity, OrderTransactionStates};
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

use function array_key_first;
use function usort;

final readonly class BlikRetryPaymentService
{
    public function __construct(
        private PaymentProcessor $paymentProcessor,
        private OrderTransactionRepositoryInterface $orderTransactionRepository,
        private OrderRepositoryInterface $orderRepository,
        private OrderOwnershipGuard $orderOwnershipGuard,
        private RouterInterface $router,
        private EnhancedLogger $logger,
    ) {
    }

    public function process(BlikRetryPaymentRequestDTO $requestDTO): BlikPaymentResponseDTO
    {
        $context = $requestDTO->salesChannelContext->getContext();
        $newOrderTransactionId = null;

        // Object-level authorization before we create any transaction or talk to the
        // gateway: the order must belong to the current customer + sales channel.
        // This route is reachable by guests and via the storefront proxy, so the
        // check lives here to cover every caller. Neutral failure, no existence leak.
        $order = $this->orderRepository->getOrderWithTransactions($requestDTO->orderId, $context);

        if ($order === null || !$this->orderOwnershipGuard->isOrderOwnedByContext($order, $requestDTO->salesChannelContext)) {
            return new BlikPaymentResponseDTO(
                success: false,
                errorMessage: 'BLIK retry failed',
                orderId: $requestDTO->orderId,
            );
        }

        // WT-800 double-charge guard: this route is only reached after a BLIK
        // transaction was already created for the order (the gateway push was
        // already sent). If that primary transaction has since settled, opening a
        // second BLIK transaction would charge the customer twice — short-circuit
        // straight to the finish page instead of creating another one.
        $primaryTransaction = $order->getPrimaryOrderTransaction() ?? $this->getLatestTransaction($order);

        if ($primaryTransaction?->getStateMachineState()?->getTechnicalName() === OrderTransactionStates::STATE_PAID) {
            return new BlikPaymentResponseDTO(
                success: true,
                redirectUrl: $requestDTO->finishUrl ?? $this->router->generate(
                    'frontend.checkout.finish.page',
                    ['orderId' => $requestDTO->orderId],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                orderId: $requestDTO->orderId,
            );
        }

        try {
            $newOrderTransactionId = $this->orderTransactionRepository->createRetryTransactionForOrder(
                $requestDTO->orderId,
                $context,
            );

            $finishUrl = $requestDTO->finishUrl ?? $this->router->generate(
                'frontend.checkout.finish.page',
                ['orderId' => $requestDTO->orderId],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $errorUrl = $requestDTO->errorUrl ?? $this->router->generate(
                'frontend.checkout.finish.page',
                ['orderId' => $requestDTO->orderId, 'paymentFailed' => true],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $response = $this->paymentProcessor->pay(
                orderId: $requestDTO->orderId,
                request: $requestDTO->request,
                salesChannelContext: $requestDTO->salesChannelContext,
                finishUrl: $finishUrl,
                errorUrl: $errorUrl,
            );

            if ($response === null) {
                // PaymentProcessor refused to pay the new transaction. Mark it
                // failed so the next retry creates yet another OT instead of
                // re-picking this orphaned one as the primary OPEN.
                $this->orderTransactionRepository->markTransactionFailed($newOrderTransactionId, $context);

                return new BlikPaymentResponseDTO(
                    success: false,
                    errorMessage: 'BLIK retry failed: payment processor returned no transaction',
                    orderId: $requestDTO->orderId,
                );
            }

            return new BlikPaymentResponseDTO(
                success: true,
                redirectUrl: $response->getTargetUrl(),
                orderId: $requestDTO->orderId,
            );
        } catch (Throwable $e) {
            $this->logger->error('BLIK retry payment failed', [
                'orderId' => $requestDTO->orderId,
                'exception' => $e,
            ]);

            if ($newOrderTransactionId !== null) {
                try {
                    $this->orderTransactionRepository->markTransactionFailed($newOrderTransactionId, $context);
                } catch (Throwable $cleanupError) {
                    $this->logger->error('Failed to mark retry OrderTransaction as failed', [
                        'orderTransactionId' => $newOrderTransactionId,
                        'exception' => $cleanupError,
                    ]);
                }
            }

            return new BlikPaymentResponseDTO(
                success: false,
                errorMessage: 'BLIK retry failed',
                orderId: $requestDTO->orderId,
            );
        }
    }

    private function getLatestTransaction(OrderEntity $order): ?OrderTransactionEntity
    {
        $transactions = $order->getTransactions();

        if ($transactions === null) {
            return null;
        }

        $elements = $transactions->getElements();

        if ($elements === []) {
            return null;
        }

        usort(
            $elements,
            static fn (OrderTransactionEntity $a, OrderTransactionEntity $b): int => ($b->getCreatedAt()?->getTimestamp() ?? 0) <=> ($a->getCreatedAt()?->getTimestamp() ?? 0),
        );

        return $elements[array_key_first($elements)];
    }
}
