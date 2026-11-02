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

use App\Entity\User;

/**
 * Expected vs. actual working time for one user over one period, in seconds.
 *
 * Formatting lives in PHP on purpose: Twig macros imported in a block are out
 * of scope inside `{% embed %}`, and with strict_variables off that fails
 * silently with empty cells.
 */
final class MissingHoursRow
{
    public function __construct(
        private readonly User $user,
        private readonly int $expectedTime,
        private readonly int $actualTime,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getExpectedTime(): int
    {
        return $this->expectedTime;
    }

    public function getActualTime(): int
    {
        return $this->actualTime;
    }

    /**
     * Negative when hours are missing.
     */
    public function getDifference(): int
    {
        return $this->actualTime - $this->expectedTime;
    }

    public function isMissing(): bool
    {
        return $this->getDifference() < 0;
    }

    public function hasContract(): bool
    {
        return $this->expectedTime > 0;
    }

    public function getExpectedFormatted(): string
    {
        return self::formatDuration($this->expectedTime);
    }

    public function getActualFormatted(): string
    {
        return self::formatDuration($this->actualTime);
    }

    public function getDifferenceFormatted(): string
    {
        return self::formatDuration($this->getDifference());
    }

    public static function formatDuration(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '';
        $seconds = abs($seconds);

        return \sprintf('%s%d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /**
     * Decimal hours (two places) for CSV and emails.
     */
    public static function toHours(int $seconds): float
    {
        return round($seconds / 3600, 2);
    }
}
