<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Infrastructure\Entity\StoredCard\{StoredCardCollection, StoredCardEntity};
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use DateTimeImmutable;
use Defuse\Crypto\{Crypto, Key};
use Defuse\Crypto\Exception\{EnvironmentIsBrokenException, WrongKeyOrModifiedCiphertextException};
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsFilter, MultiFilter};
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Lock\LockFactory;
use Throwable;
use TypeError;

use function count;
use function ctype_digit;
use function hash;
use function preg_match;
use function strlen;
use function substr;
use function trim;

/**
 * Shared storage for encrypted card tokens (brand/tail/expiry), used by gateways
 * that persist reusable card tokens.
 *
 * The token is encrypted with a dedicated, randomly generated key (defuse) that
 * lives in Shopware's PRIVATE filesystem — never in the database. This matters:
 * gateway API credentials are stored (plaintext) in system_config i.e. the DB, so
 * if the encryption key were also in the DB a single dump would expose both the
 * tokens and the means to charge them. Keeping the key out of the DB means a
 * DB-only leak yields unusable ciphertext.
 *
 * Each row records a fingerprint of the key it was encrypted with. When the key is
 * lost or rotated (fingerprint mismatch against the current key), the row is pruned
 * rather than kept as undecryptable junk — we deliberately accept losing saved
 * cards over keeping a key-laden backup. Pruning only ever happens when the current
 * key is successfully loaded, so a transient key-unavailability never deletes cards.
 */
final class StoredCardService
{
    private const KEY_PATH = 'crehler-payment/card-encryption.key';

    private ?Key $cachedKey = null;

    public function __construct(
        private readonly EntityRepository $crehlerPaymentStoredCardRepository,
        private readonly EnhancedLogger $logger,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function saveFromNotification(
        string $token,
        string $brand,
        string $tail,
        string $expiryDate,
        string $customerId,
        string $salesChannelId,
        Context $context,
    ): void {
        if (preg_match('/^\d{4}$/', $tail) !== 1) {
            $this->logger->warning('StoredCard: invalid card tail format', [
                'tail' => $tail,
                'customerId' => $customerId,
            ]);

            return;
        }

        if ($brand === '' || strlen($brand) > 50) {
            $this->logger->warning('StoredCard: invalid card brand', [
                'brand' => $brand,
                'customerId' => $customerId,
            ]);

            return;
        }

        $parsedExpiry = $this->parseExpiryDate($expiryDate);

        if ($parsedExpiry === null) {
            $this->logger->warning('StoredCard: invalid expiry date format', [
                'expiryDate' => $expiryDate,
            ]);

            return;
        }

        [$month, $year] = $parsedExpiry;

        $key = $this->getOrCreateKey();
        $tokenHash = hash('sha256', $token);
        $encryptedToken = Crypto::encrypt($token, $key);
        $fingerprint = $this->fingerprint($key);

        $existing = $this->findExistingCard($tokenHash, $customerId, $salesChannelId, $context);

        if ($existing !== null) {
            $this->crehlerPaymentStoredCardRepository->update([
                [
                    'id' => $existing->getId(),
                    'token' => $encryptedToken,
                    'tokenHash' => $tokenHash,
                    'brand' => $brand,
                    'tail' => $tail,
                    'expirationMonth' => $month,
                    'expirationYear' => $year,
                    'keyFingerprint' => $fingerprint,
                ],
            ], $context);

            $this->logger->info('StoredCard: updated existing card', [
                'storedCardId' => $existing->getId(),
                'customerId' => $customerId,
            ]);

            return;
        }

        try {
            $this->crehlerPaymentStoredCardRepository->create([
                [
                    'id' => Uuid::randomHex(),
                    'customerId' => $customerId,
                    'salesChannelId' => $salesChannelId,
                    'token' => $encryptedToken,
                    'tokenHash' => $tokenHash,
                    'brand' => $brand,
                    'tail' => $tail,
                    'expirationMonth' => $month,
                    'expirationYear' => $year,
                    'keyFingerprint' => $fingerprint,
                ],
            ], $context);
        } catch (UniqueConstraintViolationException) {
            $this->logger->info('StoredCard: duplicate card token, skipping', [
                'customerId' => $customerId,
            ]);

            return;
        }

        $this->logger->info('StoredCard: saved new card token', [
            'customerId' => $customerId,
            'brand' => $brand,
            'tail' => $tail,
        ]);
    }

    public function findById(string $id, Context $context): ?StoredCardEntity
    {
        return $this->crehlerPaymentStoredCardRepository->search(new Criteria([$id]), $context)->first();
    }

    /**
     * Ownership-scoped lookup: returns the card only if it belongs to the given
     * customer (and sales channel, when provided). Centralizes the IDOR guard so
     * callers cannot accidentally read another customer's card by id. Prunes the
     * row when it was encrypted under a stale key.
     */
    public function findByIdForCustomer(
        string $id,
        string $customerId,
        ?string $salesChannelId,
        Context $context,
    ): ?StoredCardEntity {
        $card = $this->findById($id, $context);

        if ($card === null || $card->getCustomerId() !== $customerId) {
            return null;
        }

        if ($salesChannelId !== null && $card->getSalesChannelId() !== $salesChannelId) {
            return null;
        }

        if ($this->pruneIfStaleKey($card, $context)) {
            return null;
        }

        return $card;
    }

    /**
     * Ownership-scoped delete. Returns false (no-op) when the card does not exist
     * or is not owned by the customer — so a hostile id cannot delete another
     * customer's card.
     */
    public function deleteForCustomer(
        string $id,
        string $customerId,
        ?string $salesChannelId,
        Context $context,
    ): bool {
        if ($this->findByIdForCustomer($id, $customerId, $salesChannelId, $context) === null) {
            return false;
        }

        $this->delete($id, $context);

        return true;
    }

    public function findActiveByCustomerAndChannel(
        string $customerId,
        string $salesChannelId,
        Context $context,
    ): StoredCardCollection {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('customerId', $customerId),
                new EqualsFilter('salesChannelId', $salesChannelId),
            ])
        );

        /** @var StoredCardCollection $cards */
        $cards = $this->crehlerPaymentStoredCardRepository->search($criteria, $context)->getEntities();

        $live = new StoredCardCollection();

        foreach ($this->filterExpiredCards($cards) as $card) {
            if ($this->pruneIfStaleKey($card, $context)) {
                continue;
            }

            $live->add($card);
        }

        return $live;
    }

    public function delete(string $id, Context $context): void
    {
        $this->crehlerPaymentStoredCardRepository->delete([['id' => $id]], $context);
    }

    /**
     * Total number of stored cards. Used by the reset command to size the warning
     * before any destructive action (no key side effect).
     */
    public function countStoredCards(Context $context): int
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $this->crehlerPaymentStoredCardRepository->search($criteria, $context)->getTotal();
    }

    /**
     * Ensure a card key exists (create if missing) and delete every stored card that
     * was encrypted under a different key. Returns the number pruned. Intended for a
     * deliberate, confirmed reset after the private-filesystem key was lost/restored.
     */
    public function pruneStaleCards(Context $context): int
    {
        $currentFingerprint = $this->fingerprint($this->getOrCreateKey());

        // Page through the table instead of hydrating every stored card at once —
        // a full-table search() would risk OOM exactly on a large-dataset reset,
        // when this command is needed most.
        $staleIds = [];
        $limit = 500;
        $offset = 0;

        do {
            $criteria = new Criteria();
            $criteria->setLimit($limit);
            $criteria->setOffset($offset);

            /** @var StoredCardCollection $cards */
            $cards = $this->crehlerPaymentStoredCardRepository->search($criteria, $context)->getEntities();

            foreach ($cards as $card) {
                if ($card->getKeyFingerprint() !== $currentFingerprint) {
                    $staleIds[] = ['id' => $card->getId()];
                }
            }

            $batchCount = $cards->count();
            $offset += $limit;
        } while ($batchCount === $limit);

        if ($staleIds !== []) {
            $this->crehlerPaymentStoredCardRepository->delete($staleIds, $context);
        }

        return count($staleIds);
    }

    public function decryptToken(StoredCardEntity $card): string
    {
        $key = $this->loadKeyForRead();

        if ($key === null) {
            // Key missing or temporarily unreadable — do NOT treat as a wrong key.
            $this->logger->warning('StoredCard: card key unavailable; cannot decrypt', [
                'storedCardId' => $card->getId(),
            ]);

            return '';
        }

        if ($card->getKeyFingerprint() !== $this->fingerprint($key)) {
            // Encrypted under a different/lost key — unreadable. Pruning is done by the
            // ownership-scoped lookups (which carry a Context); here we just refuse.
            return '';
        }

        try {
            return Crypto::decrypt((string) $card->getToken(), $key);
        } catch (WrongKeyOrModifiedCiphertextException|EnvironmentIsBrokenException|TypeError) {
            // Never log the exception itself — its message/trace can leak key material.
            $this->logger->warning('StoredCard: token decryption failed', [
                'storedCardId' => $card->getId(),
            ]);

            return '';
        }
    }

    /**
     * Delete the row when it was encrypted under a key other than the current one
     * (lost/rotated key) and return true. Returns false — never deleting — when the
     * current key cannot be loaded, so a transient key outage never wipes cards.
     */
    private function pruneIfStaleKey(StoredCardEntity $card, Context $context): bool
    {
        $key = $this->loadKeyForRead();

        if ($key === null) {
            return false;
        }

        if ($card->getKeyFingerprint() === $this->fingerprint($key)) {
            return false;
        }

        $this->delete($card->getId(), $context);
        $this->logger->info('StoredCard: pruned card encrypted under a stale key', [
            'storedCardId' => $card->getId(),
        ]);

        return true;
    }

    /**
     * Encryption path: load the key, generating + persisting it once if the file
     * genuinely does not exist. Never called from a read path.
     */
    private function getOrCreateKey(): Key
    {
        if ($this->cachedKey !== null) {
            return $this->cachedKey;
        }

        // Serialize first-time provisioning: without a lock two concurrent first saves
        // both see fileExists()===false, each generate a different key, and the second
        // write clobbers the first — cards encrypted under the lost key later fail the
        // fingerprint check and get pruned. The blocking lock + re-check inside makes
        // exactly one request create the key; the rest read it back.
        $lock = $this->lockFactory->createLock('crehler_payment.card_encryption_key');
        $lock->acquire(true);

        try {
            if ($this->privateFilesystem->fileExists(self::KEY_PATH)) {
                $this->cachedKey = Key::loadFromAsciiSafeString(trim($this->privateFilesystem->read(self::KEY_PATH)));

                return $this->cachedKey;
            }

            $key = Key::createNewRandomKey();
            $this->privateFilesystem->write(self::KEY_PATH, $key->saveToAsciiSafeString());
            $this->cachedKey = $key;

            return $key;
        } finally {
            $lock->release();
        }
    }

    /**
     * Read path: returns the current key, or null when it is unavailable — whether
     * because the file does not exist OR because reading/parsing it failed. Callers
     * MUST treat null as "key unavailable" (refuse / skip), never as "wrong key",
     * and never generate a key here: that would orphan every existing row.
     */
    private function loadKeyForRead(): ?Key
    {
        if ($this->cachedKey !== null) {
            return $this->cachedKey;
        }

        try {
            if (!$this->privateFilesystem->fileExists(self::KEY_PATH)) {
                return null;
            }

            $this->cachedKey = Key::loadFromAsciiSafeString(trim($this->privateFilesystem->read(self::KEY_PATH)));

            return $this->cachedKey;
        } catch (Throwable $e) {
            $this->logger->error('StoredCard: failed to read card encryption key', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Identity fingerprint of the key — used only to tell "same key" from "different
     * key", never as a security boundary. Preimage resistance is irrelevant (the
     * input is a 256-bit random key); only collision resistance matters, so the fast
     * non-cryptographic xxh128 (128-bit) is the right tool, not SHA-256.
     */
    private function fingerprint(Key $key): string
    {
        return hash('xxh128', $key->saveToAsciiSafeString());
    }

    /**
     * @return array{int, int}|null [month, year] or null if invalid
     */
    private function parseExpiryDate(string $expiryDate): ?array
    {
        if (strlen($expiryDate) !== 4 || !ctype_digit($expiryDate)) {
            return null;
        }

        $month = (int) substr($expiryDate, 0, 2);
        $year = 2000 + (int) substr($expiryDate, 2, 2);

        if ($month < 1 || $month > 12) {
            return null;
        }

        return [$month, $year];
    }

    private function findExistingCard(
        string $tokenHash,
        string $customerId,
        string $salesChannelId,
        Context $context,
    ): ?StoredCardEntity {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('tokenHash', $tokenHash),
                new EqualsFilter('customerId', $customerId),
                new EqualsFilter('salesChannelId', $salesChannelId),
            ])
        );

        return $this->crehlerPaymentStoredCardRepository->search($criteria, $context)->first();
    }

    private function filterExpiredCards(StoredCardCollection $cards): StoredCardCollection
    {
        $now = new DateTimeImmutable();
        $currentMonth = (int) $now->format('n');
        $currentYear = (int) $now->format('Y');

        $filtered = new StoredCardCollection();

        foreach ($cards as $card) {
            $year = $card->getExpirationYear();
            $month = $card->getExpirationMonth();

            if ($year === null || $month === null) {
                continue;
            }

            if ($year > $currentYear) {
                $filtered->add($card);
            } elseif ($year === $currentYear && $month >= $currentMonth) {
                $filtered->add($card);
            }
        }

        return $filtered;
    }
}
