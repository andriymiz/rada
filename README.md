# RADA

An internal information system for Zboriv City Council, designed to digitize and automate work processes. The project is being developed as a modular platform, with functionality added incrementally to meet the council's needs.

## Current Features

- A landing page.
- Admin panel sign-in at `/panel`.

## Technology Stack

- PHP 8.3+
- Laravel 13
- Filament 5
- Livewire 4 and Livewire Blaze
- Tailwind CSS 4
- Vite Plus
- SQLite as the default local database

## Requirements

To run the project locally, you need:

- PHP 8.3 or later with the extensions required by Laravel;
- Composer;
- Node.js and npm;
- SQLite for the default database configuration.

## Local Setup

```bash
git clone https://github.com/andriymiz/rada.git
cd rada
composer run setup
composer run dev
```

The `setup` script installs PHP and JavaScript dependencies, creates `.env` if needed, generates the application key, runs database migrations, and builds the frontend assets. The `dev` script starts the local development environment configured in `composer.json`.

If the SQLite database file has not been created yet:

```bash
touch database/database.sqlite
php artisan migrate
```

After startup, open the URL displayed by `composer run dev` (usually `http://localhost:8000`). The public page is available at `/`, and the admin panel at `/panel`.

## Useful Commands

```bash
# Run code style checks, static analysis, and tests
composer test

# Run the Laravel test suite only
php artisan test

# Check formatting without applying changes
composer run lint:check

# Update Laravel Boost resources for AI agents
php artisan boost:update
```

## Project Structure

- `app/Filament/` — admin panel pages;
- `app/Models/` — Eloquent models;
- `app/Providers/Filament/` — Filament panel configuration;
- `database/migrations/` — database migrations;
- `resources/views/` — Blade templates;
- `resources/css/` — application styles and the Filament theme;
- `routes/` — application routes;
- `tests/` — automated tests.

## Planned Development

The technical specification describes the gradual addition of modules and shared capabilities, including:

- role-based access control and an organizational structure;
- roll-call voting;
- registers for council and executive committee decisions, mayoral orders, and meeting minutes;
- management of sessions, council members, and committees;
- data import, export, and publication of open datasets;
- change logging and integrations with external systems.

These are planned areas of development, not features available in the current version.

## Contributing

Before making changes, run the following command before submitting changes:

```bash
composer test
```
