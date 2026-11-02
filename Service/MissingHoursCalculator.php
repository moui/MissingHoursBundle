<?php

declare(strict_types=1);

/*
 * This file is part of the "MissingHoursBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\MissingHoursBundle\Service;

use App\Entity\User;
use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\MissingHoursBundle\Model\MissingHoursRow;
use KimaiPlugin\MissingHoursBundle\Model\Period;

/**
 * Expected and actual hours come from Kimai's WorkingTimeService — the same
 * numbers as the "Working times" page — rather than from the raw work_monday…
 * preferences. That means locked (approved) months keep their snapshot values,
 * and a user's work start / last working day are respected.
 */
final class MissingHoursCalculator
{
    public function __construct(private readonly WorkingTimeService $workingTimes)
    {
    }

    public function calculate(User $user, Period $period): MissingHoursRow
    {
        return $this->calculateMany($user, [$period])[0];
    }

    /**
     * Several periods for one user, reading each calendar year from Kimai once
     * (the dashboard widget asks for three overlapping periods).
     *
     * @param list<Period> $periods
     * @return list<MissingHoursRow>
     */
    public function calculateMany(User $user, array $periods): array
    {
        if ($periods === []) {
            return [];
        }

        $timezone = new \DateTimeZone($user->getTimezone());
        $firstDay = min(array_map(static fn (Period $p): string => $p->getFrom()->format('Y-m-d'), $periods));
        $lastDay = max(array_map(static fn (Period $p): string => $p->getTo()->format('Y-m-d'), $periods));
        $until = new \DateTimeImmutable($lastDay . ' 23:59:59', $timezone);
        $expected = array_fill(0, \count($periods), 0);
        $actual = array_fill(0, \count($periods), 0);

        for ($year = (int) substr($firstDay, 0, 4); $year <= (int) substr($lastDay, 0, 4); $year++) {
            $yearDate = new \DateTimeImmutable($year . '-01-01 00:00:00', $timezone);

            foreach ($this->workingTimes->getYear($user, $yearDate, $until)->getMonths() as $month) {
                foreach ($month->getDays() as $day) {
                    $workingTime = $day->getWorkingTime();
                    if ($workingTime === null) {
                        continue;
                    }

                    $key = $day->getDay()->format('Y-m-d');
                    foreach ($periods as $index => $period) {
                        if ($period->contains($key)) {
                            $expected[$index] += $workingTime->getExpectedTime();
                            $actual[$index] += $workingTime->getActualTime();
                        }
                    }
                }
            }
        }

        $rows = [];
        foreach (array_keys($periods) as $index) {
            $rows[] = new MissingHoursRow($user, $expected[$index], $actual[$index]);
        }

        return $rows;
    }

    /**
     * @param iterable<User> $users
     * @return list<MissingHoursRow>
     */
    public function calculateAll(iterable $users, Period $period, bool $onlyMissing = false): array
    {
        $rows = [];

        foreach ($users as $user) {
            $row = $this->calculate($user, $period);

            if ($onlyMissing && !$row->isMissing()) {
                continue;
            }

            $rows[] = $row;
        }

        // Largest shortfall first.
        usort($rows, static fn (MissingHoursRow $a, MissingHoursRow $b): int => $a->getDifference() <=> $b->getDifference());

        return $rows;
    }
}
