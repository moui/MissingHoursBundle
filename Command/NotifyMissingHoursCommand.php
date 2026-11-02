<?php

declare(strict_types=1);

/*
 * This file is part of the "MissingHoursBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\MissingHoursBundle\Command;

use KimaiPlugin\MissingHoursBundle\Model\MissingHoursRow;
use KimaiPlugin\MissingHoursBundle\Model\Period;
use KimaiPlugin\MissingHoursBundle\Service\MissingHoursCalculator;
use KimaiPlugin\MissingHoursBundle\Service\MissingHoursNotifier;
use KimaiPlugin\MissingHoursBundle\Service\UserScope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'kimai:missing-hours:notify',
    description: 'Email supervisors (and optionally each user) about missing working hours'
)]
final class NotifyMissingHoursCommand extends Command
{
    public function __construct(
        private readonly UserScope $scope,
        private readonly MissingHoursCalculator $calculator,
        private readonly MissingHoursNotifier $notifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('period', null, InputOption::VALUE_REQUIRED, 'last-week or last-month (ignored when --from/--to are given)', 'last-week')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'First day, YYYY-MM-DD')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Last day, YYYY-MM-DD')
            ->addOption('supervisor', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Supervisor email (repeatable); receives the summary')
            ->addOption('notify-users', null, InputOption::VALUE_NONE, 'Also email every user with missing hours (supervisors in BCC)')
            ->addOption('send', null, InputOption::VALUE_NONE, 'Actually send; without this flag the command only prints what it would send')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timezone = new \DateTimeZone(date_default_timezone_get());
        $today = new \DateTimeImmutable('today', $timezone);
        $from = $input->getOption('from');
        $from = \is_string($from) ? $from : '';
        $to = $input->getOption('to');
        $to = \is_string($to) ? $to : '';

        try {
            if ($from !== '' || $to !== '') {
                $period = Period::fromStrings($from, $to, $timezone);
            } else {
                $period = match ($input->getOption('period')) {
                    'last-week' => Period::lastWeek($today),
                    'last-month' => Period::lastMonth($today),
                    default => null,
                };
            }
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        if ($period === null) {
            $io->error('Use --period=last-week|last-month, or both --from and --to as YYYY-MM-DD.');

            return Command::INVALID;
        }

        $supervisors = [];
        foreach ((array) $input->getOption('supervisor') as $supervisor) {
            $supervisor = \is_string($supervisor) ? trim($supervisor) : '';
            if ($supervisor !== '') {
                $supervisors[] = $supervisor;
            }
        }
        $notifyUsers = (bool) $input->getOption('notify-users');

        if (\count($supervisors) === 0 && !$notifyUsers) {
            $io->error('Nobody to notify: pass at least one --supervisor, or --notify-users.');

            return Command::INVALID;
        }

        $rows = $this->calculator->calculateAll($this->scope->listTracked(), $period, true);

        $io->title('Missing hours ' . $period->getLabel());
        $io->table(['User', 'Email', 'Expected', 'Actual', 'Difference'], array_map(
            static fn (MissingHoursRow $row): array => [
                $row->getUser()->getDisplayName(),
                $row->getUser()->getEmail(),
                $row->getExpectedFormatted(),
                $row->getActualFormatted(),
                $row->getDifferenceFormatted(),
            ],
            $rows
        ));

        if (\count($rows) === 0) {
            $io->success('No users with missing hours; nothing to send.');

            return Command::SUCCESS;
        }

        $emails = [];
        if (\count($supervisors) > 0) {
            array_push($emails, ...$this->notifier->buildSummaries($rows, $period, $supervisors));
        }
        if ($notifyUsers) {
            foreach ($rows as $row) {
                $email = $this->notifier->buildReminder($row, $period, $supervisors);
                if ($email === null) {
                    $io->warning(\sprintf('%s has no email address; skipped.', $row->getUser()->getDisplayName()));
                    continue;
                }
                $emails[] = $email;
            }
        }

        if ($input->getOption('send') !== true) {
            foreach ($emails as $email) {
                $io->section($email->getSubject() . ' → ' . implode(', ', array_map(static fn ($a) => $a->getAddress(), $email->getTo())));
                $io->writeln((string) $email->getTextBody());
            }
            $io->note(\sprintf('Dry-run: %d email(s) would be sent. Add --send to send them.', \count($emails)));

            return Command::SUCCESS;
        }

        $failed = 0;
        foreach ($emails as $email) {
            try {
                $this->notifier->send($email);
            } catch (\Throwable $e) {
                $failed++;
                $io->error(\sprintf('Failed to send "%s": %s', $email->getSubject(), $e->getMessage()));
            }
        }

        if ($failed > 0) {
            return Command::FAILURE;
        }

        $io->success(\sprintf('Sent %d email(s).', \count($emails)));

        return Command::SUCCESS;
    }
}
