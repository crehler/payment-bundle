<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Repository;

use Crehler\PaymentBundle\Domain\Contract\RuleRepositoryPort;
use Crehler\PaymentBundle\Domain\Entity\PaymentRule;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class RuleRepository implements RuleRepositoryPort
{
    public function __construct(
        private readonly EntityRepository $ruleRepository,
        private readonly Context $context,
    ) {
    }

    public function upsert(PaymentRule $rule): void
    {
        $data = $rule->toArray();

        $this->ruleRepository->upsert([$data], $this->context);
    }

    public function findById(string $id): ?PaymentRule
    {
        $criteria = new Criteria([$id]);

        /** @var RuleEntity */
        $result = $this->ruleRepository->search($criteria, $this->context)->first();

        if (!$result) {
            return null;
        }

        return new PaymentRule(
            $result->getId(),
            $result->getName(),
            $result->getPriority(),
            $result->getDescription(),
        );
    }
}
