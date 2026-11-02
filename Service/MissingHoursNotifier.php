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
use App\Mail\KimaiMailer;
use App\Repository\UserRepository;
use KimaiPlugin\MissingHoursBundle\Model\MissingHoursRow;
use KimaiPlugin\MissingHoursBundle\Model\Period;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds and sends the reminder emails. Each email is written in its
 * recipient's language: users get their own Kimai language, supervisor
 * addresses use the language of the Kimai user with that email, or the
 * default locale when there is none.
 */
final class MissingHoursNotifier
{
    private const DOMAIN = 'email';

    public function __construct(
        private readonly KimaiMailer $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $users,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
    }

    /**
     * One summary per language: supervisors sharing a language receive the
     * same email, with every one of them in "To".
     *
     * @param list<MissingHoursRow> $rows
     * @param list<string> $supervisors
     * @return list<Email>
     */
    public function buildSummaries(array $rows, Period $period, array $supervisors): array
    {
        $byLocale = [];
        foreach ($supervisors as $address) {
            $byLocale[$this->localeForAddress($address)][] = $address;
        }

        $emails = [];
        foreach ($byLocale as $locale => $addresses) {
            $emails[] = $this->buildSummary($rows, $period, $addresses, $locale);
        }

        return $emails;
    }

    /**
     * @param list<MissingHoursRow> $rows
     * @param list<string> $supervisors
     */
    public function buildSummary(array $rows, Period $period, array $supervisors, string $locale): Email
    {
        $dates = ['%from%' => $this->date($period->getFrom(), $locale), '%to%' => $this->date($period->getTo(), $locale)];

        $lines = [
            $this->trans('missing_hours.summary.heading', $dates, $locale),
            '',
            $this->trans('missing_hours.summary.intro', [], $locale),
            '',
        ];

        foreach ($rows as $row) {
            $lines[] = $this->trans('missing_hours.summary.line', [
                '%name%' => $row->getUser()->getDisplayName(),
                '%email%' => $row->getUser()->getEmail() ?? $this->trans('missing_hours.summary.no_email', [], $locale),
                '%expected%' => $this->hours($row->getExpectedTime()),
                '%actual%' => $this->hours($row->getActualTime()),
                '%difference%' => $this->hours($row->getDifference()),
            ], $locale);
        }

        $lines[] = '';
        $lines[] = $this->trans('missing_hours.summary.report_link', ['%url%' => $this->reportUrl($period, $locale)], $locale);

        return (new Email())
            ->to(...$supervisors)
            ->subject($this->trans('missing_hours.summary.subject', $dates, $locale))
            ->text(implode("\n", $lines));
    }

    /**
     * @param list<string> $supervisors BCC'd on every reminder
     */
    public function buildReminder(MissingHoursRow $row, Period $period, array $supervisors): ?Email
    {
        $user = $row->getUser();
        $email = $user->getEmail();

        if ($email === null || $email === '') {
            return null;
        }

        $locale = $this->localeForUser($user);
        $lines = [
            $this->trans('missing_hours.reminder.greeting', ['%name%' => $user->getDisplayName()], $locale),
            '',
            $this->trans('missing_hours.reminder.intro', [
                '%from%' => $this->date($period->getFrom(), $locale),
                '%to%' => $this->date($period->getTo(), $locale),
            ], $locale),
            '',
            $this->trans('missing_hours.reminder.expected', ['%hours%' => $this->hours($row->getExpectedTime())], $locale),
            $this->trans('missing_hours.reminder.actual', ['%hours%' => $this->hours($row->getActualTime())], $locale),
            $this->trans('missing_hours.reminder.difference', ['%hours%' => $this->hours($row->getDifference())], $locale),
            '',
            $this->trans('missing_hours.reminder.action', [], $locale),
            $this->trans('missing_hours.reminder.contact', [], $locale),
            '',
            $this->trans('missing_hours.reminder.thanks', [], $locale),
            '',
            $this->trans('missing_hours.reminder.link', ['%url%' => $this->timesheetUrl($locale)], $locale),
        ];

        $message = (new Email())
            ->to($email)
            ->subject($this->trans('missing_hours.reminder.subject', [], $locale))
            ->text(implode("\n", $lines));

        if (\count($supervisors) > 0) {
            $message->bcc(...$supervisors);
        }

        return $message;
    }

    public function send(Email $email): void
    {
        $this->mailer->send($email);
    }

    private function localeForUser(User $user): string
    {
        $language = $user->getLanguage();

        return $language !== '' ? $language : $this->defaultLocale;
    }

    private function localeForAddress(string $address): string
    {
        $user = $this->users->findOneBy(['email' => $address]);

        return $user instanceof User ? $this->localeForUser($user) : $this->defaultLocale;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $key, array $parameters, string $locale): string
    {
        return $this->translator->trans($key, $parameters, self::DOMAIN, $locale);
    }

    private function timesheetUrl(string $locale): string
    {
        return $this->urls->generate('timesheet', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function reportUrl(Period $period, string $locale): string
    {
        return $this->urls->generate('missing_hours_report', [
            '_locale' => $locale,
            'from' => $period->getFrom()->format('Y-m-d'),
            'to' => $period->getTo()->format('Y-m-d'),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function date(\DateTimeInterface $date, string $locale): string
    {
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, $date->getTimezone());

        return (string) $formatter->format($date);
    }

    private function hours(int $seconds): string
    {
        return number_format(MissingHoursRow::toHours($seconds), 2, '.', '');
    }
}
