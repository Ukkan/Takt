# Takt — Claude Code Guide

## Project Overview

Takt is a **Symfony 8.0** multi-tenant work time tracking application. Companies register employees, employees clock in/out, and managers review/edit time entries. An admin panel (Sonata Admin) manages all data.

## Tech Stack

- **PHP 8.4** / **Symfony 8.0**
- **Doctrine ORM 3** with Migrations
- **MySQL 8** (database service in Docker)
- **FrankenPHP** (PHP server, Caddy web server) via Docker
- **Sonata Admin Bundle 4** — admin panel at `/admin`
- **Twig** — HTML templates
- **Bootstrap 5.3** — frontend (CDN, no build step needed)

## Running the App

The app runs inside Docker. All PHP/Symfony CLI commands must be run via `docker exec`:

```bash
# Start
docker compose up -d

# Run migrations
docker exec -it takt-php-1 php bin/console doctrine:migrations:migrate

# Load fixtures
docker exec -it takt-php-1 php bin/console doctrine:fixtures:load

# Create a user interactively
docker exec -it takt-php-1 php bin/console app:create-user

# Clear cache
docker exec -it takt-php-1 php bin/console cache:clear

# Generate migration after entity changes
docker exec -it takt-php-1 php bin/console doctrine:migrations:diff
```

Access the app at `https://localhost`. Accept the self-signed TLS certificate.

## Directory Structure

```
src/
  Admin/        Sonata Admin classes (one per entity)
  Command/      CLI commands (app:create-user, app:close-stale-entries)
  Controller/   HTTP controllers
  DataFixtures/ Demo data (AppFixtures)
  Entity/       Doctrine entities
  Form/         Symfony form types
  Repository/   Doctrine repositories
  Service/      Business logic services
config/
  packages/     Bundle configs (doctrine, security, twig, etc.)
  routes/       Route imports
  services.yaml DI container
migrations/     Doctrine migration files
templates/      Twig templates
  _partials/    Reusable card components (e.g. monthly_summary_card)
  partials/     Layout partials (_sidebar, _topbar)
  admin/        Admin stats page
  manager/      Manager dashboard and forms
  time_entry/   Employee clock-in/out pages
  vacation/     Vacation request pages
  security/     Login page
```

## Authentication & Roles

Security is configured in `config/packages/security.yaml`.

| Role            | Access                  |
|-----------------|-------------------------|
| `ROLE_EMPLOYEE` | `/employee/*` — clock in/out, view own entries |
| `ROLE_MANAGER`  | `/manager/*` — manage employee entries          |
| `ROLE_ADMIN`    | `/admin-stats`, `/admin/*` — full Sonata admin  |

Role hierarchy: `ROLE_ADMIN` → `ROLE_MANAGER` → `ROLE_EMPLOYEE`.

## Multi-Tenancy

Every entity belongs to a `Company`. Controllers always scope queries by the current user's company. Never fetch entities cross-company.

## Entity Relationships

```
Company ──< User
Company ──< Employee >── User
Company ──< TimeEntry >── Employee
Company ──< Shift >── Employee
Company ──< OvertimeRecord >── Employee
Company ──< Message (sender/recipient) >── User
Company ──< Notification >── User
Company ──< ChangeLog
```

## Entities

### TimeEntry
- `endTime === null` means the employee is **currently clocked in**. Only one active entry per employee.
- `getDurationMinutes()` → `(endTime - startTime) - breakMinutes`
- `isActive()` → `endTime === null`
- `source` enum values: `'app'` (clocked via UI), `'manual'` (added by manager), `'import'` (external)

### Employee
- Links a `User` to a `Company` with contract details
- `contractMinutesPerWeek` — e.g., 2400 = 40 h/week. Defaults to 480 min/day (8 h) if not set.
- `hiredAt` / `terminatedAt` — for employment period tracking

### Shift
- Used for both **scheduled shifts** and **vacation requests**
- `type` — `'shift'`, `'vacation'`, etc.
- `status` — `'pending'`, `'approved'`, `'rejected'` (relevant for vacations)
- Approved vacations are subtracted from expected work time in summaries

### OvertimeRecord
- Pre-computed overtime/deficit per employee per period
- `overtimeMinutes` (positive) and `deficitMinutes` (positive separately) per `(periodStart, periodEnd)` range
- Unique constraint: `(employee_id, period_start, period_end)`

## Service Layer

### WorkTimeCalculatorService
Computes work time summaries.

**Public methods:**
- `computeCurrentMonthToDate(Employee)` — period: 1st of month to today
- `computeMonthSummary(Employee, int $year, int $month)` — full calendar month
- `computePeriodSummary(Employee, DateTimeImmutable $from, DateTimeImmutable $to)` — arbitrary range

**All return an array with:**
- `workedMinutes` — sum of completed `TimeEntry` durations
- `expectedMinutes` — `effectiveWorkdays × dailyRate`
- `netMinutes` — `workedMinutes - expectedMinutes` (positive = overtime, negative = deficit)
- `approvedVacationDays` — Mon–Fri days covered by approved vacation Shifts
- `periodStart`, `periodEnd`

**Calculation logic:**
1. Count workdays (Mon–Fri) in period
2. Count approved vacation overlap days (Mon–Fri only)
3. `effectiveWorkdays = totalWorkdays - approvedVacationDays`
4. `dailyRate = contractMinutesPerWeek / 5` (default 480)
5. `expectedMinutes = effectiveWorkdays × dailyRate`

### StaleTimeEntryCloser
Closes time entries left open past midnight (`endTime` set to the midnight after `startTime`, meta flag `auto_closed`).
- `isStale(TimeEntry)`, `closeAtMidnight(TimeEntry)` (caller flushes), `closeAllStale()` (flushes)
- Invoked lazily on clock-in/clock-out and by the `app:close-stale-entries` command (intended for a daily cron)

## Repositories — Custom Methods

### EmployeeRepository
- `findByUser(User)` — employee linked to a user
- `findByCompany(Company)` — all company employees, eager-load users, sorted by name

### TimeEntryRepository
- `findActiveEntry(Employee)` — active entry (`endTime IS NULL`)
- `findRecentForEmployee(Employee, int $limit=20)` — recent entries
- `countClockedInNow()` — count globally active entries
- `countStartedToday()` — count entries started today
- `findStaleActiveEntries(DateTimeInterface $before)` — active entries started before the given boundary (left open past midnight)
- `sumWorkedMinutesForPeriod(Employee, DateTimeImmutable $start, DateTimeImmutable $end)` — sum of completed entry durations (end is exclusive)

### ShiftRepository
- `findApprovedVacationsInPeriod(Employee, $start, $end)` — approved vacations overlapping date range
- `findVacationRequestsForEmployee(Employee)` — all vacation shifts, any status, newest first
- `findPendingVacationsForCompany(Company)` — pending requests across all employees

## Admin Classes

All Sonata Admin classes are in `src/Admin/` and registered in `config/services.yaml` with the `sonata.admin` tag.

- `UserAdmin` — handles password hashing in `prePersist`/`preUpdate` via `UserPasswordHasherInterface`
- `CompanyAdmin`
- `EmployeeAdmin`
- `TimeEntryAdmin`

## Form Types

- `TimeEntryType` — `startTime`, `endTime`, `breakMinutes`. Used by both employee manual log and manager edit.
- `VacationRequestType` — maps to `Shift` entity, fields: `startTime` (date), `endTime` (date), `note`.

## Controllers

| Controller | Prefix | Role required |
|---|---|---|
| `SecurityController` | `/login`, `/home`, `/logout` | public |
| `TimeEntryController` | `/employee/time` | ROLE_EMPLOYEE |
| `VacationController` | `/employee/vacation` | ROLE_EMPLOYEE |
| `ManagerController` | `/manager` | ROLE_MANAGER |
| `AdminController` | `/admin-stats` | ROLE_ADMIN |

`ManagerController::denyAccessUnlessCompanyMatch(Employee)` — always call this when handling employees to enforce company isolation.

`SecurityController::home` redirects to the correct dashboard based on highest role.

## Key Patterns

- **Active time entry**: Only one `TimeEntry` with `endTime === null` per employee at a time. Clock-in prevents duplicate if one exists. Clock-out finds the active one.
- **Vacation as Shift**: Vacation requests are `Shift` records with `type='vacation'`, `status='pending'`. Approved ones affect `WorkTimeCalculatorService` calculations.
- **Duration calculation**: `getDurationMinutes()` on `TimeEntry` returns `(endTime - startTime) - breakMinutes`. Only completed entries (non-null `endTime`) are summed for period totals.
- **Password hashing**: done in `UserAdmin::prePersist/preUpdate` using `UserPasswordHasherInterface`.
- **Summary route params**: `TimeEntryController::summary` and `ManagerController::employee_time` accept `?year=&month=` query params; default to current month.

## Demo Credentials (after fixtures load)

| Email                  | Password   | Role     |
|------------------------|------------|----------|
| admin@example.com      | password   | Admin    |
| manager@example.com    | password   | Manager  |
| employee@example.com   | password   | Employee |

## File Editing

Use Read/Write/Edit tools directly — no need to go through Docker for file changes. Docker exec is only needed for running PHP/Symfony CLI commands.
