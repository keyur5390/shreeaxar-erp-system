# Shree Axar ERP

Full-stack Laravel + Vue quotation management scaffold for a furniture company.

## Stack

- Laravel 12 (PHP 8.2+) with Eloquent, Sanctum SPA auth, Storage public disk, Scheduler, Cache, throttle rate limiting, Mail, Intervention Image, DomPDF, Purifier, Spatie Permission, and Spatie Activitylog.
- Vue 3 Composition API with TypeScript, Vite, Pinia, Vue Router 4, TanStack Vue Query, shadcn-vue-ready Tailwind CSS v3, VeeValidate/Yup, Chart.js, vue-draggable-next, and Lucide icons.
- MySQL 8.0 with `utf8mb4_unicode_ci` collation.

## Prerequisites

Install these before setup:

| Tool | Version |
|------|---------|
| PHP | 8.2 or higher |
| Composer | 2.x |
| Node.js | 20 LTS recommended (avoid Node 24+ for frontend tooling compatibility) |
| npm | 10+ |
| MySQL | 8.0+ |

Required PHP extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`.

## Project setup

### 1. Clone and install dependencies

```bash
git clone <repository-url> shreeaxar-erp-system
cd shreeaxar-erp-system

composer install
npm install
```

### 2. Environment file

Copy the example env file and generate an application key:

**Linux / macOS**
```bash
cp .env.example .env
php artisan key:generate
```

**Windows (PowerShell / CMD)**
```powershell
copy .env.example .env
php artisan key:generate
```

### 3. Configure `.env`

Open `.env` and set your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shreeaxar_erp
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

Keep these values for local SPA development:

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
VITE_API_URL=http://localhost:8000/api
VITE_STORAGE_URL=http://localhost:8000/storage
```

Optional: configure `MAIL_*` settings if you need OTP / quotation emails (Mailtrap works well for local testing).

### 4. Create the database

Create an empty MySQL database named `shreeaxar_erp`.

**Linux / macOS (MySQL CLI)**
```bash
mysql -u root -p -e "CREATE DATABASE shreeaxar_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Windows**

Use MySQL Workbench, phpMyAdmin, or the MySQL CLI:

```sql
CREATE DATABASE shreeaxar_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Laravel storage and database

```bash
php artisan storage:link
php artisan migrate
php artisan db:seed
```

`db:seed` loads roles, permissions, master data, sample records, and the default admin user.

To reset and reseed from scratch:

```bash
php artisan migrate:fresh --seed
```

## Running the project

You can run the app **traditionally** (PHP + Node on your machine) or with **Docker** (recommended for consistent setup).

### Option A — Traditional (local PHP + Node)

Development requires **two terminals** running at the same time.

**Terminal 1 — Laravel API**
```bash
php artisan serve
```
Runs at `http://localhost:8000`

**Terminal 2 — Vue frontend (Vite)**
```bash
npm run dev
```
Runs at `http://localhost:5173`

Open the app in your browser at **`http://localhost:5173`**.

The Vite dev server proxies `/api` and `/sanctum` requests to Laravel at `http://localhost:8000`. Keep `SANCTUM_STATEFUL_DOMAINS=localhost:5173` (including the port) so Sanctum CSRF cookies work for SPA auth.

### Option B — Docker (development)

#### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows / macOS) or Docker Engine + Docker Compose v2 (Linux)

#### Quick start

```bash
# 1. Copy Docker environment file
cp .env.docker.example .env.docker

# 2. Build and start all services (MySQL, PHP, Nginx, Vite, Scheduler)
docker compose --env-file .env.docker up --build
```

Or with Make:

```bash
cp .env.docker.example .env.docker
make up
```

#### What gets started

| Service | Container | URL / Port | Purpose |
|---------|-----------|------------|---------|
| `mysql` | shreeaxar-mysql | `localhost:3307` | MySQL 8 database |
| `app` | shreeaxar-app | internal | PHP 8.4-FPM (Laravel) |
| `nginx` | shreeaxar-nginx | `http://localhost:8001` | API + web entry |
| `node` | shreeaxar-node | `http://localhost:5174` | Vite dev server (frontend) |
| `scheduler` | shreeaxar-scheduler | internal | `php artisan schedule:work` |

Open **`http://localhost:5174`** for the frontend (same as traditional dev).

On first startup the `app` container automatically:

- waits for MySQL
- runs `composer install`
- generates `APP_KEY` if missing
- runs `php artisan storage:link`
- runs `php artisan migrate`
- runs `php artisan db:seed` (set `RUN_SEED=false` in `.env.docker` to skip reseeding)

#### Useful Docker commands

```bash
# Start in background
docker compose --env-file .env.docker up -d

# View logs
docker compose logs -f

# Stop all services
docker compose down

# Run artisan inside the app container
docker compose exec app php artisan route:list

# Fresh database reset + seed
docker compose exec app php artisan migrate:fresh --seed --force

# Open a shell in the app container
docker compose exec app sh

# Remove containers and database volume (full reset)
docker compose down -v
```

#### Docker + traditional together

Both modes can coexist on the same machine if ports differ. The default Docker stack uses ports `8001`, `5174`, and `3307`. Stop Docker (`docker compose down`) before running the traditional setup on ports `8000`, `5173`, and `3306`, or change `APP_PORT`, `VITE_PORT`, and `DB_PORT` in `.env.docker`.

### Option C — Docker (production-like)

Builds frontend assets into the image and serves everything through Nginx on a single port.

```bash
cp .env.docker.example .env.docker

# Adjust for production-like mode in .env.docker:
# APP_ENV=production
# APP_DEBUG=false
# APP_URL=http://localhost:8080
# APP_PORT=8080
# FRONTEND_URL=http://localhost:8080
# SANCTUM_STATEFUL_DOMAINS=localhost:8080
# RUN_SEED=true

docker compose -f docker-compose.prod.yml --env-file .env.docker up --build -d
```

Open **`http://localhost:8080`**.

Or with Make:

```bash
make prod-up
```

### Default login (after seeding)

| Field | Value |
|-------|-------|
| Email | `admin@vytech.co` |
| Password | `admin@private` |

Change this password before deploying to any shared or production environment.

## Useful commands

```bash
# Frontend type checking
npm run typecheck

# Production frontend build
npm run build

# List API routes
php artisan route:list

# Clear application cache
php artisan optimize:clear

# Run scheduled tasks locally (OTP cleanup, quotation reminders)
php artisan schedule:work
```

## Health check

After the API is running:

```bash
curl http://localhost:8000/api/health
```

If `HEALTH_TOKEN` is set in `.env`, pass it as a header:

```bash
curl -H "X-Health-Token: your-secret-token" http://localhost:8000/api/health
```

## Troubleshooting

**`php artisan` fails with `Target class [files] does not exist`**

Ensure `config/app.php` does not contain an empty `providers` array, and that `bootstrap/providers.php` and `app/Providers/AppServiceProvider.php` exist.

**Database connection errors**

Verify MySQL is running and that `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` are correct.

**Login or API requests fail with CSRF / 419 errors**

- Use `http://localhost:5173` (not `:8000`) as the frontend URL during development.
- Confirm `SANCTUM_STATEFUL_DOMAINS=localhost:5173` in `.env`.
- Restart both `php artisan serve` and `npm run dev` after env changes.

**`npm run build` or `npm run typecheck` fails**

Use Node.js 20 LTS. If `vue-tsc` reports a TypeScript export error, pin compatible versions in `package.json` (for example `typescript@~5.7` and a matching `vue-tsc` release) and run `npm install` again.

**Blank page or missing assets**

Run `npm run dev` and open `http://localhost:5173`. The Blade shell loads the Vite dev server during local development.

**Permission denied on `storage/` or `bootstrap/cache/`**

Ensure those directories are writable by the web server / PHP process. In Docker, the entrypoint script creates and chmods these automatically.

**Docker: `app` container keeps restarting**

Check logs with `docker compose logs app`. Usually MySQL is not ready yet or DB credentials in `.env.docker` do not match the `mysql` service.

**Docker: port already in use**

Change `APP_PORT`, `VITE_PORT`, or `DB_PORT` in `.env.docker` (defaults: `8001`, `5174`, `3307`), or stop the conflicting local service.

**Docker: slow first startup**

The first `docker compose up --build` installs Composer and npm dependencies inside containers. Subsequent starts are much faster.

## Project status

This repository is a scaffold. Auth, health checks, models, migrations, and seeders are in place. Several API controllers and frontend pages are still placeholders—see route definitions in `routes/api.php` and page components under `resources/js/pages/`.
