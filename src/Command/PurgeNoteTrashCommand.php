<?php

namespace App\Command;

use App\Entity\Note;
use App\Repository\NoteRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Supprime définitivement les notes restées à la corbeille plus de
 * Note::TRASH_RETENTION_DAYS jours. À planifier (cron quotidien) en production :
 * rien ne la lance automatiquement.
 */
#[AsCommand(name: 'app:note:purge-trash', description: 'Supprime définitivement les notes à la corbeille depuis plus de 30 jours.')]
final class PurgeNoteTrashCommand extends Command
{
    public function __construct(private readonly NoteRepository $noteRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = new \DateTimeImmutable(sprintf('-%d days', Note::TRASH_RETENTION_DAYS));
        $deleted = $this->noteRepository->purgeTrashedBefore($limit);

        $io->success(sprintf('%d note(s) supprimée(s) définitivement.', $deleted));

        return Command::SUCCESS;
    }
}
