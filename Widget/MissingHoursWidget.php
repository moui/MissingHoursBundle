<?php

declare(strict_types=1);

/*
 * This file is part of the "MissingHoursBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\MissingHoursBundle\Widget;

use App\Widget\Type\AbstractWidget;
use App\Widget\WidgetInterface;
use KimaiPlugin\MissingHoursBundle\Model\Period;
use KimaiPlugin\MissingHoursBundle\Service\MissingHoursCalculator;

/**
 * Each user's own balance for the periods that matter to the
 * weekly reminder. Only completed days count, so a Monday morning does not
 * show a full day as missing.
 */
final class MissingHoursWidget extends AbstractWidget
{
    public const ID = 'MissingHoursWidget';

    public function __construct(private readonly MissingHoursCalculator $calculator)
    {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getTitle(): string
    {
        return 'missing_hours.widget.title';
    }

    public function getWidth(): int
    {
        return WidgetInterface::WIDTH_HALF;
    }

    public function getHeight(): int
    {
        return WidgetInterface::HEIGHT_MEDIUM;
    }

    public function getTemplateName(): string
    {
        return '@MissingHours/widget.html.twig';
    }

    /**
     * @param array<string, string|bool|int|null|array<string, mixed>> $options
     * @return list<array{label: string, period: Period|null, row: \KimaiPlugin\MissingHoursBundle\Model\MissingHoursRow|null}>
     */
    public function getData(array $options = []): mixed
    {
        $today = new \DateTimeImmutable('today', $this->getTimezone());
        $periods = [
            'missing_hours.period.last_week' => Period::lastWeek($today),
            'missing_hours.period.this_week_to_yesterday' => Period::thisWeekToYesterday($today),
            'missing_hours.period.this_month_to_yesterday' => Period::thisMonthToYesterday($today),
        ];

        $complete = array_filter($periods);
        $rows = $complete === [] ? [] : array_combine(
            array_keys($complete),
            $this->calculator->calculateMany($this->getUser(), array_values($complete))
        );

        $data = [];
        foreach ($periods as $label => $period) {
            $data[] = ['label' => $label, 'period' => $period, 'row' => $rows[$label] ?? null];
        }

        return $data;
    }
}
