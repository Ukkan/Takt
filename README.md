# Takt

A multi-tenant employee work time tracking application built with **Symfony 8**, **Doctrine ORM**, and **FrankenPHP** (Docker).

## Features

- **Clock in / Clock out** — employees track work time from a simple UI
- **Manual time entry** — managers can add or edit entries on behalf of employees
- **Break time tracking** — subtract break minutes from total worked time
- **Monthly summaries** — worked vs. expected hours with overtime/deficit indicators
- **Vacation requests** — employees request leave, managers approve or reject
- **Shift scheduling** — shifts and absences tracked alongside time entries
- **Manager dashboard** — see all employees and their current clock-in status at a glance
- **Role-based access** — Employee, Manager, Admin roles with hierarchy
- **Multi-tenant** — each company's data is fully isolated
- **Sonata Admin panel** — full CRUD for all entities at `/admin`
- **Internal messaging** — messages and notifications between users
- **Audit trail** — change log for all entity modifications
- **Overtime records** — pre-computed overtime and deficit minutes per period

## Requirements

- [Docker](https://www.docker.com/) with Compose v2.10+

## Setup

```bash
# 1. Clone the repository
git clone <repo-url>
cd Takt

# 2. Build and start Docker containers
docker compose build --pull --no-cache
docker compose up --wait

# 3. Run database migrations
docker exec -it takt-php-1 php bin/console doctrine:migrations:migrate

# 4. (Optional) Load demo data
docker exec -it takt-php-1 php bin/console doctrine:fixtures:load
```

Open **https://localhost** in your browser. Accept the auto-generated TLS certificate.

## Demo Accounts

After loading fixtures:

| Email                  | Password   | Role     |
|------------------------|------------|----------|
| admin@example.com      | password   | Admin    |
| manager@example.com    | password   | Manager  |
| employee@example.com   | password   | Employee |

## Usage

### Employee

1. Log in as an employee
2. Go to **Time Tracking** (`/employee/time`)
3. Click **Clock In** to start tracking
4. Click **Clock Out** to stop
5. Use **Log Time** to manually add past entries
6. View **Monthly Summary** for worked vs. expected hours
7. Go to **Vacations** (`/employee/vacation`) to request leave

### Manager

1. Log in as a manager
2. Go to **Manager Dashboard** (`/manager`)
3. View all employees — a green indicator means currently clocked in
4. Click an employee to view their monthly summary and edit time entries
5. Go to **Vacation Requests** to approve or reject pending leave

### Admin

1. Log in as an admin
2. Go to **Admin Panel** (`/admin`) for full CRUD (companies, users, employees, time entries)
3. Go to **Stats** (`/admin-stats`) for system-wide statistics (total companies, users, entries today, clocked in now)

## Creating Users via CLI

```bash
docker exec -it takt-php-1 php bin/console app:create-user
```

Prompts for email, full name, role, password, and optional company assignment.

## Architecture

```
Company (tenant)
 ├── Users (with roles: employee, manager, admin)
 ├── Employees (links User to Company, stores contract details)
 ├── TimeEntries (clock in/out records)
 ├── Shifts (scheduled shifts + vacation requests)
 └── OvertimeRecords (pre-computed overtime per period)
```

**Work time calculation** (`WorkTimeCalculatorService`):
- Expected minutes = effective workdays (Mon–Fri, minus approved vacation days) × daily rate
- Daily rate = `contractMinutesPerWeek / 5` (default 8 h)
- Net minutes = worked − expected (positive = overtime, negative = deficit)

## Development

```bash
# Stop containers
docker compose down

# View logs
docker compose logs -f php

# Clear Symfony cache
docker exec -it takt-php-1 php bin/console cache:clear

# Generate a new migration after entity changes
docker exec -it takt-php-1 php bin/console doctrine:migrations:diff

# Apply pending migrations
docker exec -it takt-php-1 php bin/console doctrine:migrations:migrate
```

## Tech Stack

| Layer       | Technology                        |
|-------------|-----------------------------------|
| Language    | PHP 8.4                           |
| Framework   | Symfony 8.0                       |
| ORM         | Doctrine ORM 3 + Migrations       |
| Database    | MySQL 8                           |
| Server      | FrankenPHP (Caddy)                |
| Admin       | Sonata Admin Bundle 4             |
| Templates   | Twig + Bootstrap 5                |
| Runtime     | Docker / Docker Compose           |

## License

All rights reserved. This is the source code of an engineering thesis project,
published so that it can be read and reviewed. It is not open source and may not
be reused without written permission. See [LICENSE](LICENSE) for the full notice,
including the third-party components that keep their original MIT license.
