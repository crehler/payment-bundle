<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Command;

use Crehler\PaymentBundle\Application\Service\StoredCardService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Post-restore reset for stored-card encryption.
 *
 * After the private-filesystem key is lost (e.g. disk failure) and the DB is
 * restored, the old card rows are unreadable. This regenerates the key if missing
 * and deletes every card not encrypted under the current key, so the saved-card
 * list is clean immediately instead of healing only when a customer next saves one.
 *
 * Destructive: stored cards are permanently deleted (customers re-enter them).
 * Guarded by an interactive confirmation; non-interactive runs MUST pass --force.
 */
#[AsCommand(
    name: 'crehler:payment:reset-card-key',
    description: 'Regenerate the stored-card encryption key if missing and prune cards encrypted under a lost/old key',
)]
final class ResetCardKeyCommand extends Command
{
    public function __construct(
        private readonly StoredCardService $storedCardService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Skip the interactive confirmation (required for non-interactive/scripted runs).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $force = (bool) $input->getOption('force');
        $total = $this->storedCardService->countStoredCards($context);

        $io->warning(sprintf(
            "This regenerates the card-encryption key if it is missing and PERMANENTLY DELETES every stored card\n"
            . "that is not encrypted under the current key. There are currently %d stored card(s).\n"
            . 'Affected customers will have to re-enter their card. This cannot be undone.',
            $total,
        ));

        // Fail closed in non-interactive contexts (cron/CI) unless the operator
        // explicitly opted in with --force, so this can never wipe cards by accident.
        if (!$force && !$input->isInteractive()) {
            $io->error('Refusing to run a destructive reset non-interactively. Re-run with --force if this is intended.');

            return Command::FAILURE;
        }

        if (!$force && !$io->confirm('Proceed with the card-key reset?', false)) {
            $io->note('Aborted. No changes were made.');

            return Command::SUCCESS;
        }

        $pruned = $this->storedCardService->pruneStaleCards($context);

        $io->success(sprintf('Card-key reset complete. Pruned %d stale card(s).', $pruned));

        return Command::SUCCESS;
    }
}
