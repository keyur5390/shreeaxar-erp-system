# Shree Axar ERP

Full-stack Laravel + Vue quotation management scaffold for a furniture company.

## Stack
- Laravel 12 (PHP 8.2+) with Eloquent, Sanctum SPA auth, Storage public disk, Scheduler, Cache, throttle rate limiting, Mail, Intervention Image, DomPDF, Purifier, Spatie Permission, and Spatie Activitylog.
- Vue 3 Composition API with TypeScript, Vite, Pinia, Vue Router 4, TanStack Vue Query, shadcn-vue-ready Tailwind CSS v3, VeeValidate/Yup, Chart.js, vuedraggable, and Lucide icons.
- MySQL 8.0 with `utf8mb4_unicode_ci` collation.

## Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan storage:link
mysql -e "CREATE DATABASE shreeaxar_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
php artisan serve
npm run dev
```

The development frontend runs at `http://localhost:5173` and proxies `/api` and `/sanctum` requests to Laravel at `http://localhost:8000`. Keep `SANCTUM_STATEFUL_DOMAINS=localhost:5173` including the port for SPA CSRF cookies.
