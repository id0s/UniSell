# UniSell Digital

Platform agregator & billing produk digital multi-supplier dengan satu backend Laravel API yang melayani Storefront (Nuxt 3) dan Admin Console (Vue 3 + Vite).

---

## Struktur Monorepo

```text
.
├── apps/
│   ├── api/            # Laravel 12 REST API (/api/v1), Sanctum Auth, RBAC, Queue
│   ├── storefront/     # Nuxt 3 + Tailwind (Landing Page, Katalog, Checkout, Lacak Order)
│   └── admin/          # Vue 3 + Vite + Pinia + TanStack Query (Dashboard, Suppliers, Products, Orders, Theme Manager)
├── packages/
│   └── ui/             # UniSell Theme System v1.0 (10 preset Light/Dark + CSS variables bersama)
├── docker/             # Dockerfile PHP-FPM & konfigurasi Nginx untuk VPS
├── docs/               # Keputusan arsitektur (ADR)
├── scripts/            # Script backup & restore database harian
└── docker-compose.yml  # Orkestrasi layanan produksi (api, queue, mysql, redis, nginx)
```

---

## Prasyarat (Build Lokal Native)

- **PHP 8.2+** dengan ekstensi: `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, `redis`, `sqlite3`
- **Composer 2.x**
- **Node.js 20 / 22+** & **npm**
- **MariaDB / MySQL 8+**
- **Redis 7+**
- **Nginx** (opsional, untuk menyatukan Storefront, Admin, dan API dalam satu URL)

---

## 1. Setup Awal & Install Dependency

```bash
git clone git@github.com:id0s/UniSell.git
cd UniSell

# 1. Install dependency frontend monorepo (packages/ui, apps/admin, apps/storefront)
npm install

# 2. Install dependency backend Laravel
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
```

---

## 2. Konfigurasi Database & Redis (`apps/api/.env`)

Buat database MySQL/MariaDB terlebih dahulu:

```sql
CREATE DATABASE unisell CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'unisell'@'127.0.0.1' IDENTIFIED BY 'unisell_secret';
GRANT ALL PRIVILEGES ON unisell.* TO 'unisell'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Lalu sesuaikan `apps/api/.env`:

```env
APP_NAME="UniSell Digital"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=unisell
DB_USERNAME=unisell
DB_PASSWORD=unisell_secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Jalankan migrasi & seeder (membuat 20 tabel, role, akun default, dan katalog demo):

```bash
cd apps/api
php artisan migrate --seed
```

**Akun default hasil seeder:**
- **Admin:** `admin@unisell.local` / `Admin12345!`
- **Staff:** `staff@unisell.local` / `Staff12345!`
- **Customer:** `customer@unisell.local` / `Customer12345!`

---

## 3. Detail Cara Build Produksi

Jalankan dari **root folder proyek**:

### A. Build Admin Console (`apps/admin`)
```bash
npm run build:admin
```
- Menggunakan Vite dengan `base: '/admin/'` ([vite.config.ts](apps/admin/vite.config.ts)).
- Output static SPA tersimpan di `apps/admin/dist/` (siap disajikan langsung oleh Nginx pada path `/admin/`).

### B. Build Storefront & Landing Page (`apps/storefront`)
```bash
NUXT_TELEMETRY_DISABLED=1 npm run build:storefront
```
- Menggunakan Nuxt 3 Nitro (`node-server` preset) dengan proxy otomatis `/api/**` → `http://127.0.0.1:8000/api/**`.
- Output server produksi tersimpan di `apps/storefront/.output/`.

### C. Optimasi Backend Laravel (`apps/api` — Opsional Produksi)
```bash
cd apps/api
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 4. Menjalankan Aplikasi

### Mode Produksi Lokal (Disarankan — Satu URL via Nginx `:8080`)

Jalankan 3 proses utama:

```bash
# Terminal 1 — Laravel REST API (:8000)
cd apps/api && php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2 — Redis Queue Worker (untuk auto-delivery pesanan)
cd apps/api && php artisan queue:work redis --sleep=1 --tries=3

# Terminal 3 — Nuxt Storefront Production Server (:3000)
HOST=0.0.0.0 PORT=3000 node apps/storefront/.output/server/index.mjs
```

Dengan Nginx reverse proxy di port `8080`:
- `/` → proxy ke `http://127.0.0.1:3000` (Landing Page & Storefront)
- `/admin/` → static alias ke `apps/admin/dist/` (Admin SPA)
- `/api/` → proxy ke `http://127.0.0.1:8000/api/` (Laravel API)

### Mode Development (Hot Reload)
```bash
# Admin Vue dev server (:5173)
npm run dev:admin

# Storefront Nuxt dev server (:3000)
npm run dev:storefront
```

### Deploy VPS dengan Docker Compose
```bash
docker compose up -d --build
docker compose exec api php artisan migrate --seed --force
```

---

## 5. Menjalankan Automated Test

```bash
cd apps/api
php artisan test
```

Mencakup pengujian:
- Auth & RBAC (`admin`, `staff`, `customer`)
- Enkripsi kredensial supplier & anti-kebocoran di API
- Auto-lock/unlock produk saat langganan supplier aktif/berakhir
- Webhook payment signature & idempotency (webhook ganda hanya membuat 1 supplier order)
- Retry supplier 3× exponential backoff → `manual_review` → `refunded`
- Driver supplier: **VIP-Reseller**, **Digiflazz**, **SekaliPay**, dan **Manual**
- Perhitungan laba bersih, simulasi break-even (BEP), dan laporan evaluasi supplier

---

## 6. Backup & Restore Database

```bash
# Buat backup harian (tersimpan di backups/ dalam format .sql.gz, retensi 14 hari)
./scripts/backup-db.sh

# Restore dari file backup
./scripts/restore-db.sh backups/unisell_YYYYMMDD_HHMMSS.sql.gz
```
