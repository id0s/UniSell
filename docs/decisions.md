# Architectural & Technical Decisions — UniSell Digital

## ADR-001: Monorepo Structure
- **Status:** Accepted (Fase 1)
- **Decision:** Monorepo di `/home/dos/projek` dengan struktur `apps/api` (Laravel), `apps/storefront` (Nuxt), `apps/admin` (Vue 3 + Vite), `packages/ui` (shared design tokens & theme engine), `docker/`, dan `docs/`.
- **Context:** Sesuai spesifikasi [agents.md](../agents.md). Menggunakan `npm workspaces` untuk berbagi `@unisell/ui` antara `apps/admin` dan `apps/storefront`.

## ADR-002: Combined Theme System (Neon Dark Default + UniSell Theme System v1.0)
- **Status:** Accepted (Fase 1, disetujui user)
- **Decision:** Menggabungkan spesifikasi tema dari `agents.md` dan `ui.md` menjadi **UniSell Theme System v1.0** di `packages/ui`:
  - **Default preset:** `neon` (**Neon Dark** dari `agents.md`: `#0A0B14`, `#00F0FF`, `#FF2BD6`, `#B6FF3B`, glow halus) dengan pasangan Light mode.
  - **8 preset tambahan** dari `ui.md`: `emerald`, `ocean`, `indigo`, `violet`, `rose`, `amber`, `teal`, `slate` — masing-masing memiliki pasangan Light & Dark.
  - Mendukung custom colors, appearance mode (`dark`, `light`, `system`), border radius (`sm`, `md`, `lg`, `xl`), interface density (`compact`, `comfortable`, `spacious`), save custom themes, dan export/import konfigurasi JSON.
  - Konfigurasi Admin dan Storefront disimpan secara terpisah (namespace penyimpanan terpisah), menggunakan semantic CSS variables sebagai satu-satunya sumber warna.

## ADR-003: Local Native Runtime + Docker Compose for VPS
- **Status:** Accepted (Fase 1, disetujui user)
- **Decision:** Untuk pengembangan & deploy lokal di mesin ini, layanan dijalankan secara native (PHP 8.2, Composer, MariaDB/MySQL, Redis, Nginx, Node 22). File `docker-compose.yml` dan `docker/` tetap disediakan lengkap untuk deployment satu VPS di produksi.

## ADR-004: Database Schema & Integer Rupiah Invariant
- **Status:** Accepted (Fase 1)
- **Decision:** Seluruh nilai moneter (`price`, `cost_price`, `sell_price`, `markup_value`, `total`, `unit_price`, `unit_cost`, `amount`, `fee`, `balance`) disimpan sebagai `BIGINT` (integer Rupiah, bukan float). Semua tabel dari bagian 5 `agents.md` dibuat sejak Fase 1 dengan foreign key dan constraint unik (`supplier_products(supplier_id, external_id)`, `orders.order_no`, `webhook_logs.event_id`).

## ADR-005: Auth & RBAC (Sanctum)
- **Status:** Accepted (Fase 1)
- **Decision:** Menggunakan Laravel Sanctum token authentication untuk `/api/v1/auth/*` dan role-based access control (`admin`, `staff`, `customer`) via tabel `roles` dan `users.role_id`, serta middleware `role:...`. Response error diseragamkan ke format `{ "error": { "code": "...", "message": "..." } }`.

## ADR-006: Supplier & Payment Gateway Adapter Pattern (`TODO(docs)`)
- **Status:** Accepted (Fase 2 & Fase 4)
- **Decision:** Integrasi supplier dibungkus `App\Contracts\SupplierDriverInterface` (`MockApiSupplierDriver` + `ManualSupplierDriver`) dan payment gateway dibungkus `App\Contracts\PaymentGatewayInterface` (`MockPaymentGateway` dengan QRIS/VA/e-wallet + HMAC-SHA256 signature). Titik integrasi API eksternal ditandai `TODO(docs)` agar mudah ditukar saat dokumentasi resmi supplier/gateway tersedia.

## ADR-007: Webhook Idempotency, Encryption & Backup Hardening
- **Status:** Accepted (Fase 4 & Fase 7)
- **Decision:**
  - Webhook pembayaran diverifikasi signature-nya terlebih dahulu, lalu dilindungi dual idempotency gate: unique constraint `webhook_logs.event_id` + `DB::transaction` dengan `lockForUpdate()` pada `payment_transactions` dan `orders`.
  - Kredensial supplier (`supplier_credentials.payload`) dan hasil pengiriman (`deliveries.payload`) dienkripsi menggunakan Laravel encrypted casts; kredensial disembunyikan (`$hidden`) dan tidak pernah muncul di response API atau audit log.
  - Backup harian & uji restore disediakan di `scripts/backup-db.sh` dan `scripts/restore-db.sh`.

## Penghapusan Syarat Langganan Supplier (Langsung Digiflazz)
- **Keputusan**: Menghapus kewajiban paket/langganan supplier sebelum mempublikasikan produk. Supplier (seperti Digiflazz) langsung berstatus `active` dan menggunakan saldo deposit Digiflazz.
- **Dampak**: `ProductAggregatorService` tidak lagi memblokir publikasi berdasarkan `hasActiveSubscription()`, ditambah endpoint `POST /api/v1/admin/suppliers/{supplier}/publish-all` untuk publikasi massal produk hasil sync, serta pembersihan UI paket/langganan di Admin.
