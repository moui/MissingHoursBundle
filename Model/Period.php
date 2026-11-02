<?php

declare(strict_types=1);

/*
 * This file is part of the "MissingHoursBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\MissingHoursBundle\Model;

/**
 * An inclusive range of calendar days. Only the date part matters: comparisons
 * are made on Y-m-d strings so a user's timezone never shifts a day in or out.
 */
final class Period
{
    private function __construct(
        private readonly \DateTimeImmutable $from,
        private readonly \DateTimeImmutable $to,
    ) {
    }

    public static function create(\DateTimeInterface $from, \DateTimeInterface $to): self
    {
        $from = \DateTimeImmutable::createFromInterface($from)->setTime(0, 0);
        $to = \DateTimeImmutable::createFromInterface($to)->setTime(0, 0);

        if ($from > $to) {
            throw new \InvalidArgumentException('The start date must be before the end date.');
        }

        return new self($from, $to);
    }

    /**
     * Parses two Y-m-d strings; returns null if either is malformed.
     */
    public static function fromStrings(string $from, string $to, \DateTimeZone $timezone): ?self
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $from, $timezone);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $to, $timezone);

        if ($start === false || $end === false || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to) {
            return null;
        }

        return self::create($start, $end);
    }

    /**
     * Monday to Sunday of the previous calendar week.
     */
    public static function lastWeek(\DateTimeImmutable $today): self
    {
        $monday = $today->modify('monday this week')->modify('-7 days');

        return self::create($monday, $monday->modify('+6 days'));
    }

    public static function lastMonth(\DateTimeImmutable $today): self
    {
        $first = $today->modify('first day of last month');

        return self::create($first, $first->modify('last day of this month'));
    }

    /**
     * From Monday of this week through yesterday, or null on a Monday when
     * there is no completed day yet.
     */
    public static function thisWeekToYesterday(\DateTimeImmutable $today): ?self
    {
        $monday = $today->modify('monday this week');
        $yesterday = $today->modify('-1 day');

        return $yesterday < $monday ? null : self::create($monday, $yesterday);
    }

    /**
     * From the 1st of this month through yesterday, or null on the 1st.
     */
    public static function thisMonthToYesterday(\DateTimeImmutable $today): ?self
    {
        $first = $today->modify('first day of this month');
        $yesterday = $today->modify('-1 day');

        return $yesterday < $first ? null : self::create($first, $yesterday);
    }

    public function getFrom(): \DateTimeImmutable
    {
        return $this->from;
    }

    public function getTo(): \DateTimeImmutable
    {
        return $this->to;
    }

    public function contains(string $day): bool
    {
        return $day >= $this->from->format('Y-m-d') && $day <= $this->to->format('Y-m-d');
    }

    public function getLabel(): string
    {
        return $this->from->format('d/m/Y') . ' – ' . $this->to->format('d/m/Y');
    }
}
