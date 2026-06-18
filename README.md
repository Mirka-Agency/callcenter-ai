# Call Center Intelligence Platform

An AI-powered call center analytics and coaching platform built with Laravel. Organizations connect VoIP systems, analyze conversations with LLMs, track agent performance, manage customers, and bill AI usage through organization wallets.

The product ships with three user-facing surfaces:

| Portal | URL prefix | Audience |
|--------|------------|----------|
| **Employer dashboard** | `/app` | Team managers — agents, reports, intelligence, wallet |
| **Employee workspace** | `/workspace` | Call specialists — personal performance, uploads, coaching |
| **Admin panel** | `/admin` | Platform admins — organizations, billing, integrations (Filament) |

Default locale is **Persian (fa)** with RTL UI and Jalali (Shamsi) dates in the SaaS dashboards.

---

## Features

### Call intelligence
- AI conversation analysis (summary, sentiment, lead quality, concerns, coaching insights)
- Manual audio upload and processing queue with real-time status (Laravel Reverb)
- Call recording playback with waveform player
- Conversation search, filters, and detailed analysis pages

### Team & performance
- Agent (specialist) management and profiles
- Team dashboard with performance cards and tiering (top performers / needs attention)
- Agent performance reports with trends, exports (CSV, Excel, PDF)
- Employee coaching recommendations and activity feeds

### Customers & CRM
- Customer profiles with contact history and intelligence
- CRM provider integrations and organization-level connections
- Lead quality and concern tracking across calls

### VoIP & integrations
- VoIP provider catalog and per-organization connections
- Webhook ingestion for call events
- Incoming call center for employees (live sessions)

### AI billing
- Multiple LLM providers and models with configurable per-token pricing
- Organization AI wallets, transactions, and usage analytics
- Pre-analysis balance checks; cost snapshots on each analysis
- Platform-wide billing settings and cost estimator

### Administration
- Filament admin panel for users, organizations, integrations, and AI infrastructure
- User impersonation with audit logs (super admin)
- Persian number formatting and Jalali date pickers in admin forms

---

## Tech stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.3+, Laravel 13 |
| Admin UI | Filament 5 |
| SaaS dashboards | Livewire 4, Alpine.js, Tailwind CSS 4 |
| Real-time | Laravel Reverb, Laravel Echo |
| Dates | `morilog/jalali`, `ariaieboy/filament-jalali`, custom `shamsi()` helper |
| Frontend build | Vite 8 |
| Charts | Chart.js |
| Audio | WaveSurfer.js |
| Database | SQLite (default), MySQL supported |
| Queue | Database driver (default) |
| Storage | Local / S3 for recordings |

---

## Requirements

- PHP 8.3+ with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `zip`
- Composer 2
- Node.js 20+ and npm
- SQLite, or MySQL/PostgreSQL for production

---

## Local development

### Quick setup

```bash
composer setup
```

This runs `composer install`, copies `.env`, generates `APP_KEY`, migrates the database, installs npm dependencies, and builds frontend assets.

### Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Important variables:

```env
APP_URL=http://127.0.0.1:8000
APP_LOCALE=fa

DB_CONNECTION=sqlite

QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8090
REVERB_SCHEME=http

# Optional: S3-compatible storage for call recordings (required on CapRover)
RECORDINGS_DISK=s3
RECORDINGS_RETENTION_DAYS=10
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
```

### Seed demo data

```bash
php artisan db:seed
```

Default users (password from `UserFactory`, typically `password`):

| Email | Role |
|-------|------|
| `admin@example.com` | Super Admin → `/admin` |
| `admin.user@example.com` | Admin → `/admin` |
| `employer@example.com` | Employer → `/app` |
| `employee@example.com` | Employee → `/workspace` |

### Run the dev stack

Starts HTTP server, queue worker, log tail, Reverb, and Vite HMR:

```bash
composer dev
```

Or run services individually:

```bash
php artisan serve
php artisan queue:listen --tries=3 --timeout=300
php artisan reverb:start
npm run dev
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) and sign in.

---

## Docker

Multi-stage image: Composer dependencies, Vite build, PHP-FPM + Nginx via Supervisor.

```bash
docker build -t callcenter .
docker run -p 8000:8000 \
  -e APP_KEY=base64:... \
  callcenter
```

Migrations run automatically on each web container start (`php artisan migrate --force`). Set `RUN_MIGRATIONS=false` to disable.

### Container roles

The default `web` container runs **Nginx**, **PHP-FPM**, **queue worker**, and **scheduler** via Supervisor. Queue commands live in `docker/bin/queue-worker` and `docker/bin/scheduler` (single source of truth — used by both Supervisor and optional dedicated containers).

| Role | Processes |
|------|-----------|
| `web` (default) | Nginx + PHP-FPM + queue + scheduler (supervisord) |
| `queue` | Extra `queue-worker` only (scale horizontally) |
| `scheduler` | `scheduler` only |
| `reverb` | `php artisan reverb:start` |

Optional queue tuning env vars: `QUEUE_WORKER_SLEEP`, `QUEUE_WORKER_TRIES` (default 5), `QUEUE_WORKER_TIMEOUT` (default 600), `DB_QUEUE_RETRY_AFTER` (default 630 — must exceed job timeout).

Health check: `GET /up`

---

## Project structure

```
app/
├── Application/          # Use cases (LLM analysis, impersonation, …)
├── Domain/               # Enums, DTOs, domain logic
├── Filament/             # Admin panel resources & pages
├── Livewire/
│   ├── Employer/         # /app dashboard
│   ├── Employee/         # /workspace portal
│   └── Shared/           # Shared analysis & queue components
├── Models/
├── Services/             # Analytics, billing, VoIP, reports, …
└── Support/              # JalaliDate, PersianNumber, presenters

resources/
├── css/saas.css          # SaaS design system
├── js/                   # Vite entrypoints, Jalali date picker, charts
└── views/
    ├── components/saas/  # Reusable UI components
    ├── layouts/          # Employer & employee layouts
    └── livewire/         # Portal views

routes/
├── employer.php          # /app/*
├── employee.php          # /workspace/*
└── web.php               # Auth, webhooks, home redirect
```

---

## Key routes

### Employer (`/app`)
- `/app` — Team dashboard
- `/app/intelligence` — Call analysis list
- `/app/intelligence/performance` — Agent performance
- `/app/manual-analyses` — Manual upload hub
- `/app/processing-queue` — AI processing queue
- `/app/reports` — Management reports
- `/app/customers` — Customer intelligence
- `/app/employees` — Agent management
- `/app/wallet` — AI wallet & usage

### Employee (`/workspace`)
- `/workspace` — Personal performance dashboard
- `/workspace/calls` — Analyzed calls
- `/workspace/uploads` — Manual uploads
- `/workspace/coaching` — Coaching insights
- `/workspace/customers` — Assigned customers

### Webhooks & API
- `POST /webhooks/voip/{connection}` — VoIP event webhook
- `POST /api/voip/incoming-call` — Incoming call API

---

## Seeding demo data

The project includes comprehensive demo seeders with **20 organizations**, **100 demo users** (1 employer + 4 employees each), Persian names, AI wallets (**20,000 Toman / 200,000 IRR** per org), customers, **150 calls per organization** (10 dated today, the rest spread across the last 30 days), and matching conversation analyses. All demo accounts share the password **`123456789`** (hashed at seed time; demo/test only).

Demo organizations are **standalone**: they have no CRM, VoIP, or per-organization LLM connections. Call/analytics sample data is seeded locally for UI testing. All organizations use the **platform default AI provider and model** configured in admin billing settings.

```bash
php artisan migrate:fresh --seed
# or seed demo data only (after platform providers are seeded):
php artisan db:seed --class=PlatformFoundationSeeder
php artisan db:seed --class=DemoSeeder
```

### Demo login

| Pattern | Password | Portal |
|---------|----------|--------|
| `firstname.lastname@gmail.com` | `123456789` | `/app` (employer) |
| `firstname.lastname@gmail.com` | `123456789` | `/workspace` (employee) |

Example: `mohsen.rezaei@gmail.com` → organization **مرکز تماس پارسین**  
Example employee: `ali.mohammadi@gmail.com`

Super admins can add more demo employees instantly from **Admin → Organizations → Edit** (or the employee integrations tab) via **افزودن کاربر دمو دیگر**.

### Removing demo data

Demo organizations are flagged with `is_demo = true` (seeded automatically). Super admins can reset them from **Admin → Organizations** (`/admin/organizations`):

- **مدیریت داده دمو → حذف همه سازمان‌های دمو** — deletes all demo organizations and cascaded data (users, calls, analyses, wallet transactions, etc.)
- **حذف داده‌های دمو** — per-organization action on the list or edit page

The confirmation modal shows an irreversible warning, a scope-protection note, and record counts. Production organizations (`is_demo = false`) are never affected.

```bash
# Re-seed after cleanup:
php artisan db:seed --class=DemoSeeder
```

### Platform admins

| Email | Password | Portal |
|-------|----------|--------|
| `admin@example.com` | `password` | `/admin` |
| `admin.user@example.com` | `password` | `/admin` |

---

## Testing

```bash
composer test
# or
php artisan test
```

---

## Production checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a strong `APP_KEY`
- Use MySQL/PostgreSQL instead of SQLite
- Run Reverb in a dedicated container if you use realtime updates (`CONTAINER_ROLE=reverb`)
- Configure S3 for `RECORDINGS_DISK` — recordings are kept until AI analysis completes, then deleted after `RECORDINGS_RETENTION_DAYS`
- If you keep local disks, mount a **persistent volume** on `/var/www/html/storage` in CapRover
- Set up LLM provider API keys in the admin panel
- Configure organization VoIP/CRM connections
- Run `php artisan config:cache`, `route:cache`, `view:cache`

---

## License

MIT


## seed demo
- Run `php artisan db:seed --class=DemoSeeder`