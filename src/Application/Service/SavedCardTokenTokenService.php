<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Domain\Contract\SavedCardTokenServicePort;
use Crehler\PaymentBundle\Domain\Entity\SavedCardCollection;
use Crehler\PaymentBundle\Domain\Repository\PaymentMethodRepositoryInterface;
use Crehler\PaymentBundle\Infrastructure\Port\SavedCardTokenProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class SavedCardTokenTokenService implements SavedCardTokenServicePort
{
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private SavedCardTokenProvider $savedCardTokenProvider,
    ) {
    }

    public function getSavedCards(string $paymentMethodId, SalesChannelContext $context): SavedCardCollection
    {
        $collection = new SavedCardCollection(savedCards: []);

        $paymentMethod = $this->paymentMethodRepository->findById(paymentMethodId: $paymentMethodId, context: $context);

        if (!$paymentMethod) {
            return $collection;
        }

        $savedCards = $this->savedCardTokenProvider->getCustomerCardTokens(paymentMethod: $paymentMethod, salesChannelContext: $context);

        // SavedCardCollection is immutable — addSavedCard() returns a new instance,
        // so the result must be reassigned (otherwise the collection stays empty).
        foreach ($savedCards as $savedCard) {
            $collection = $collection->addSavedCard(savedCard: $savedCard);
        }

        return $collection;
    }
}
