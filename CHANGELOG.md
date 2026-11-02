# Changelog

## 0.2.0

- First public release: translations (English, Spanish) for the widget, the report, the CSV
  export and the emails (`messages`, `reporting` and `email` domains).
- Emails are written in the recipient's language: users get their Kimai language; supervisor
  addresses use the language of the matching Kimai user, or the default locale. Supervisors
  with different languages receive separate summaries.
- Dates are locale-aware: the report and widget use Kimai's short date format, emails use
  the recipient's locale. Report links in the supervisor summary carry the recipient's
  locale.
- CSV column headers follow the viewer's language.
- Removed references to the previous in-house setup from code comments and docs.
- Composer package renamed to `moui/missing-hours-bundle`; MIT license, code style headers,
  CI, PHPStan level 9.

## 0.1.1

- `kimai:missing-hours:add-widget [--apply]` appends the widget to dashboards users
  already customised (the default dashboard has it since 0.1.0).

## 0.1.0

- First release, targeting Kimai 2.66 (`extra.kimai.require` 26600) and PHP 8.2+.
- Dashboard widget **My missing hours**: last week, this week and this month
  (completed days only) for the signed-in user. Added to the default dashboard.
- Report **Reporting → Missing hours** with date range, presets, summary cards and
  CSV export. Granted to `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` through the
  `missing_hours_report` permission; rows are narrowed by Kimai's team visibility.
- Console command `kimai:missing-hours:notify` emailing a summary to supervisors and,
  optionally, a reminder to each user. Dry-run unless `--send`.
