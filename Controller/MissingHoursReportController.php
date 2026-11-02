<?php

declare(strict_types=1);

/*
 * This file is part of the "MissingHoursBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\MissingHoursBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use KimaiPlugin\MissingHoursBundle\Model\MissingHoursRow;
use KimaiPlugin\MissingHoursBundle\Model\Period;
use KimaiPlugin\MissingHoursBundle\Service\MissingHoursCalculator;
use KimaiPlugin\MissingHoursBundle\Service\UserScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/reporting/missing-hours')]
#[IsGranted('view_reporting')]
#[IsGranted('missing_hours_report')]
final class MissingHoursReportController extends AbstractController
{
    public function __construct(
        private readonly MissingHoursCalculator $calculator,
        private readonly UserScope $scope,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '', name: 'missing_hours_report', methods: ['GET'])]
    public function report(Request $request): Response
    {
        $viewer = $this->getCurrentUser();
        [$period, $error] = $this->resolvePeriod($request, $viewer);
        $onlyMissing = $request->query->get('all') !== '1';

        $rows = $this->calculator->calculateAll($this->scope->listTracked($viewer), $period, $onlyMissing);
        $totalMissing = array_sum(array_map(
            static fn (MissingHoursRow $row): int => $row->isMissing() ? -$row->getDifference() : 0,
            $rows
        ));
        $missingCount = \count(array_filter($rows, static fn (MissingHoursRow $row): bool => $row->isMissing()));

        return $this->render('@MissingHours/report.html.twig', [
            'report_title' => 'missing_hours.report.title',
            'period' => $period,
            'rows' => $rows,
            'only_missing' => $onlyMissing,
            'error' => $error,
            'missing_count' => $missingCount,
            'total_missing' => MissingHoursRow::formatDuration($totalMissing),
            'average_missing' => MissingHoursRow::formatDuration($missingCount > 0 ? intdiv($totalMissing, $missingCount) : 0),
            'presets' => $this->presets($viewer),
        ]);
    }

    #[Route(path: '/export', name: 'missing_hours_report_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $viewer = $this->getCurrentUser();
        [$period] = $this->resolvePeriod($request, $viewer);
        $onlyMissing = $request->query->get('all') !== '1';
        $rows = $this->calculator->calculateAll($this->scope->listTracked($viewer), $period, $onlyMissing);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Could not open a temporary stream for the CSV export.');
        }
        $header = ['user', 'email', 'missing_hours.csv.expected_hours', 'missing_hours.csv.actual_hours', 'missing_hours.csv.difference_hours', 'status'];
        fputcsv($handle, array_map(fn (string $key): string => $this->translator->trans($key), $header), escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->getUser()->getDisplayName(),
                $row->getUser()->getEmail(),
                MissingHoursRow::toHours($row->getExpectedTime()),
                MissingHoursRow::toHours($row->getActualTime()),
                MissingHoursRow::toHours($row->getDifference()),
                $row->isMissing() ? 'MISSING' : 'OK',
            ], escape: '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $filename = \sprintf(
            'kimai_missing_hours_%s_%s.csv',
            $period->getFrom()->format('Y-m-d'),
            $period->getTo()->format('Y-m-d')
        );

        return new Response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Defaults to the previous Monday–Sunday, the period the weekly reminder
     * covers. The error, if any, is a translation key.
     *
     * @return array{0: Period, 1: string|null}
     */
    private function resolvePeriod(Request $request, User $viewer): array
    {
        $timezone = new \DateTimeZone($viewer->getTimezone());
        $today = new \DateTimeImmutable('today', $timezone);
        $from = (string) $request->query->get('from', '');
        $to = (string) $request->query->get('to', '');

        if ($from === '' && $to === '') {
            return [Period::lastWeek($today), null];
        }

        try {
            $period = Period::fromStrings($from, $to, $timezone);
        } catch (\InvalidArgumentException) {
            return [Period::lastWeek($today), 'missing_hours.error.range_order'];
        }

        if ($period === null) {
            return [Period::lastWeek($today), 'missing_hours.error.invalid_date'];
        }

        return [$period, null];
    }

    /**
     * @return array<string, Period>
     */
    private function presets(User $viewer): array
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone($viewer->getTimezone()));

        return array_filter([
            'missing_hours.period.last_week' => Period::lastWeek($today),
            'missing_hours.period.last_month' => Period::lastMonth($today),
            'missing_hours.period.this_month_to_yesterday' => Period::thisMonthToYesterday($today),
        ]);
    }

    private function getCurrentUser(): User
    {
        return $this->getUser();
    }
}
