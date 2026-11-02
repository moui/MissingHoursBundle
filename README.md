# MissingHoursBundle for Kimai

[![CI](https://github.com/moui/MissingHoursBundle/actions/workflows/linting.yaml/badge.svg)](https://github.com/moui/MissingHoursBundle/actions/workflows/linting.yaml)

A Kimai plugin that tells people how many working hours they are missing. Every user gets
a dashboard widget with their own balance, team leads and admins get a report of everyone
they are responsible for (with CSV export), and a console command emails reminders to users
and a summary to supervisors. It adds no database tables and runs no raw SQL: expected and
actual hours come from Kimai's own `WorkingTimeService`, the same numbers as the
**Working times** page.

## Features

| Piece | Who sees it | Where |
|---|---|---|
| Widget **My missing hours** | every user, own data | Dashboard (added to the default layout) |
| Report **Missing hours** | `missing_hours_report` permission | Reporting → Missing hours, `/{locale}/reporting/missing-hours` |
| Command `kimai:missing-hours:notify` | console / cron | — |
| Command `kimai:missing-hours:add-widget` | console | — |

- **Widget**: last week (Monday–Sunday), this week until yesterday and this month until
  yesterday. Today is never counted, so a Monday morning does not show a full day as
  missing. Users without a working-time contract see a hint instead of zeros.
- **Report**: defaults to the previous Monday–Sunday. Free date range, presets (last week,
  last month, this month until yesterday), summary cards (users below target, total and
  average missing hours), an option to include users who are up to date, and CSV export.
  Largest shortfall first.
- **Emails**: a summary for supervisors and an optional reminder for each user who is short,
  each written in the recipient's language. Dry-run by default.

## Who is tracked

Every enabled, non-system user **without** `ROLE_ADMIN` / `ROLE_SUPER_ADMIN`. Admins manage
the system and are not expected to book working hours.

In the report each viewer only sees the users Kimai already lets them see: admins see
everyone, team leads see the members of the teams they lead.

## How expected hours are computed

The plugin asks Kimai's `WorkingTimeService` instead of summing the `work_monday` …
`work_sunday` preferences itself. That has three consequences:

1. **The contract type must be set.** Expected hours are only non-zero for users whose
   *Working times* contract is configured. A user with daily hours filled in but no
   contract type gets 0 expected hours here, and also on Kimai's own Working times page.
2. **Locked months keep their snapshot.** Months that were locked (approved) report the
   approved values, not ones recalculated from today's contract. The
   [WorkingTimeLockBundle](https://github.com/moui/WorkingTimeLockBundle) is an optional
   companion for locking months.
3. **Contract start and last working day are respected**, and running timesheets are
   ignored.

## Requirements

- Kimai 2.66 or later
- PHP 8.2 or later

## Installation

Copy the plugin into Kimai's plugin directory and reload Kimai:

```bash
cd /opt/kimai/var/plugins/
# copy or unzip the plugin here, the directory must be named MissingHoursBundle
cd /opt/kimai && bin/console kimai:reload -n
```

Or clone it directly:

```bash
cd /opt/kimai/var/plugins/
git clone https://github.com/moui/MissingHoursBundle.git
cd /opt/kimai && bin/console kimai:reload -n
```

The plugin has no database migrations, so there is no install command.

**Docker:** mount or copy the plugin to `/opt/kimai/var/plugins/MissingHoursBundle` and run
the console as `www-data`, otherwise the cache is written with the wrong owner and Kimai
cannot read it:

```bash
docker compose exec -u www-data kimai bin/console kimai:reload -n
```

## Configuration

There is nothing to configure in Kimai's settings. What matters:

- **Working times**: each user needs a working-time contract (user profile → *Working
  times*), otherwise their expected hours are 0.
- **Mail**: emails go through Kimai's own mailer (`MAILER_URL` / `MAILER_FROM`). Verify it
  first with `bin/console kimai:mail:test you@example.com`.
- **Links** in emails are absolute URLs; set Kimai's `DEFAULT_URI` so the console knows the
  public address.

## Permissions

| Permission | Default roles | Grants |
|---|---|---|
| `missing_hours_report` | `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` | The **Missing hours** report and its CSV export (also requires `view_reporting`). Rows are limited to the users the viewer may see. |

The widget needs no extra permission: every user sees only their own hours.

## Console commands

### `kimai:missing-hours:notify`

```bash
# Dry-run (default): prints the table and every email it would send
bin/console kimai:missing-hours:notify --period=last-week \
  --supervisor=lead1@example.com --supervisor=lead2@example.com

# Send for an explicit range, also emailing each user (supervisors in BCC)
bin/console kimai:missing-hours:notify --from=2026-10-01 --to=2026-10-31 \
  --supervisor=lead1@example.com --notify-users --send
```

| Option | Meaning |
|---|---|
| `--period` | `last-week` (Monday–Sunday, default) or `last-month` |
| `--from` / `--to` | explicit range `YYYY-MM-DD`; overrides `--period` |
| `--supervisor` | repeatable; receives the summary of everyone with missing hours |
| `--notify-users` | also email each user who is short, with the supervisors in BCC |
| `--send` | actually send; otherwise dry-run |

Only users with missing hours are included; if nobody is short, nothing is sent.

Each email is written in its recipient's language. A user's reminder uses the language set
in their Kimai profile. A supervisor address is matched to the Kimai user with that email
and uses their language; unknown addresses get Kimai's default locale. Supervisors with
different languages receive separate summaries. BCC copies of a reminder are in the user's
language.

Run it from cron, for example every Monday morning for the previous week:

```cron
0 7 * * 1  cd /opt/kimai && bin/console kimai:missing-hours:notify --period=last-week --supervisor=lead1@example.com --send
```

### `kimai:missing-hours:add-widget`

The widget is added to Kimai's *default* dashboard, which every user sees until they
customise theirs. Saved custom dashboards are left alone, so add the widget to those once:

```bash
bin/console kimai:missing-hours:add-widget           # report only
bin/console kimai:missing-hours:add-widget --apply   # append it
```

It only appends to dashboards that do not have it yet; re-running is harmless.

## Translations

The plugin ships with English and Spanish, in `Resources/translations/`:

- `messages.*.xlf`: widget, report and CSV texts
- `reporting.*.xlf`: the report title in the Reporting menu
- `email.*.xlf`: the reminder and summary emails

To add a language, copy each `*.en.xlf` file to `*.<locale>.xlf`, translate the `<target>`
elements and run `bin/console kimai:reload -n`. Pull requests with new languages are
welcome.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
