<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Shared;

use Crehler\PaymentBundle\Application\Port\Driven\OrderTransactionRepositoryInterface;
use Crehler\PaymentBundle\Domain\Entity\OrderTransaction\OrderTransaction;
use RuntimeException;
use Shopware\Core\Checkout\Payment\Cart\Token\{JWTFactoryV2, TokenFactoryInterfaceV2 as TokenFactoryInterface, TokenStruct};
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

use function hash_equals;
use function hash_hmac;
use function strlen;
use function substr;
use function time;

final class FinalizeTokenService
{
    /**
     * 128-bit (32 hex chars) truncated HMAC — matches {@see UrlSigner}. The earlier
     * 64-bit signature left too little margin for a public, browser-facing payment
     * endpoint that mints fresh Shopware payment tokens.
     */
    private const SIGNATURE_LENGTH = 32;

    /**
     * Lifetime of a short browser-return URL. The signed value is bound to this
     * expiry so a leaked/captured return URL is not replayable indefinitely.
     */
    private const RETURN_URL_TTL = 1800;

    public function __construct(
        #[Autowire(service: JWTFactoryV2::class)]
        private TokenFactoryInterface $tokenFactory,
        private RouterInterface $router,
        private OrderTransactionRepositoryInterface $orderTransactionWriteRepository,
    ) {
    }

    public function buildUrl(OrderTransaction $orderTransaction): string
    {
        $token = $this->tokenFactory->generateToken(new TokenStruct(
            id: null,
            token: null,
            paymentMethodId: $orderTransaction->paymentMethod->id,
            transactionId: $orderTransaction->id,
            finishUrl: null,
            expires: 288000,
            errorUrl: null
        ));

        return $this->assembleReturnUrl(token: $token);
    }

    public function parseToken(string $token): TokenStruct
    {
        $tokenStruct = $this->tokenFactory->parseToken($token);

        if ($tokenStruct->isExpired()) {
            throw PaymentException::tokenExpired($tokenStruct->getToken());
        }

        return $tokenStruct;
    }

    /**
     * Build a short, HMAC-signed return URL for the customer's browser to come
     * back from the payment gateway. Used instead of Shopware's standard
     * /payment/finalize-transaction?_sw_payment_token=<long-JWT> URL, which is
     * routinely truncated by payment gateways with a Location-header / return-URL
     * length limit (Tpay sandbox truncates at ~512 chars, dropping the JWT
     * signature segment and producing CHECKOUT__INVALID_PAYMENT_TOKEN).
     *
     * The original finishUrl/errorUrl supplied by the storefront/Store API caller
     * are not carried by the short URL, so they are persisted on the transaction
     * here for PaymentReturnController to read back when the browser returns.
     */
    public function buildBrowserReturnUrl(
        OrderTransaction $orderTransaction,
        ?string $finishUrl = null,
        ?string $errorUrl = null,
    ): string {
        // Always write — including null/null — so a retry without URLs clears
        // stale values from an earlier attempt instead of inheriting them.
        $this->orderTransactionWriteRepository->updateReturnUrls(
            orderTransactionId: $orderTransaction->id,
            finishUrl: $finishUrl,
            errorUrl: $errorUrl,
        );

        $expiresAt = time() + self::RETURN_URL_TTL;

        return $this->router->generate(
            'crehler.bundle.payment.return',
            [
                'orderTransactionId' => $orderTransaction->id,
                'exp' => $expiresAt,
                'sig' => $this->computeSignature($orderTransaction->id, $expiresAt),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * Verify a browser-return URL: the signature must match and the bound expiry
     * must not have passed. Fail-closed on a malformed/empty signature or a stale
     * (or absent) expiry.
     */
    public function verifySignature(string $orderTransactionId, string $sig, int $expiresAt): bool
    {
        if (strlen($sig) !== self::SIGNATURE_LENGTH) {
            return false;
        }

        if ($expiresAt < time()) {
            return false;
        }

        return hash_equals($this->computeSignature($orderTransactionId, $expiresAt), $sig);
    }

    private function assembleReturnUrl(string $token): string
    {
        return $this->router->generate(
            'crehler.bundle.payment.notification',
            ['_sw_payment_token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function computeSignature(string $orderTransactionId, int $expiresAt): string
    {
        $secret = (string) EnvironmentHelper::getVariable('APP_SECRET', '');

        if ($secret === '') {
            throw new RuntimeException('APP_SECRET is not configured; cannot sign payment return URL. Refusing to fall back to an empty HMAC key (would produce a deterministic, attacker-derivable signature).');
        }

        // Bind the signature to the expiry so the URL cannot be replayed past its TTL.
        return substr(hash_hmac('sha256', $orderTransactionId . ':' . $expiresAt, $secret), 0, self::SIGNATURE_LENGTH);
    }
}
