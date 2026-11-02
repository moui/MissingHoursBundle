<?php

declare(strict_types=1);

/*
 * This file is part of the "MissingHoursBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\MissingHoursBundle\EventSubscriber;

use App\Event\DashboardEvent;
use KimaiPlugin\MissingHoursBundle\Widget\MissingHoursWidget;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts the widget on the default dashboard. Users who already customised
 * their dashboard keep it as is and can add the widget themselves.
 */
final class DashboardSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DashboardEvent::class => ['onDashboard', 100],
        ];
    }

    public function onDashboard(DashboardEvent $event): void
    {
        $event->addWidget(MissingHoursWidget::ID, 5);
    }
}
