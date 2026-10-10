# Date and time handling

## Storage and display timezones

Store timestamps in UTC. Keep Laravel's `app.timezone` set to `UTC`; do not
change the application timezone just to display local time. Filament converts
date-time values between the configured application timezone and the display
timezone, so values entered by users in Kyiv time are saved in UTC and shown
back in Kyiv time.

The MySQL and MariaDB connections use a UTC session (`+00:00`), and the
PostgreSQL connection uses a UTC session (`UTC`). Keep database connection
timezones aligned with `app.timezone` so database timestamp types and direct
SQL operations do not interpret UTC values as local time.

The Filament panel configures this default in
`app/Providers/Filament/RadaPanelProvider.php`:

```php
FilamentTimezone::set('Europe/Kyiv');
```

This default applies to Filament date-time display in table columns and
infolists, and to date-time picker fields. Date-only values are calendar dates,
not UTC instants, and must not be shifted between timezones.

## Display format

Use numeric Ukrainian date formatting consistently:

- Date: `d.m.Y` (for example, `09.10.2026`).
- Date and time in tables and infolists: `d.m.Y H:i` (for example,
  `09.10.2026 19:43`).
- Date and time in editable pickers: `d.m.Y H:i:s`, preserving seconds when a
  user edits a timestamp.
- Time only: `H:i`; time pickers that include seconds use `H:i:s`.

These defaults are configured globally in `RadaPanelProvider` for Filament
tables, schemas, and date/time pickers. Individual components should use the
default date/time methods (such as `dateTime()` or `date()`) unless a field has
a specific product requirement. ISO-format methods use matching numeric formats
with Carbon's ISO tokens.

When adding a date-time display outside Filament, explicitly convert from UTC
to `Europe/Kyiv` at the presentation boundary. Keep comparisons, persistence,
and machine-readable timestamps in UTC.
