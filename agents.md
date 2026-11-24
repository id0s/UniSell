# UniSell Digital — Spesifikasi untuk Agent

> Baca seluruh file ini sebelum menulis kode. Kerjakan **satu fase per waktu**. Setelah tiap fase selesai, berhenti, ringkas hasilnya, lalu tunggu persetujuan.

## 0. Ringkasan

**UniSell Digital** = platform agregator produk digital multi-supplier.
Satu backend (Laravel API) melayani banyak channel: Website (Nuxt), Admin (Vue SPA), Telegram, WhatsApp.

Alur inti: pembeli bayar → sistem validasi → pesanan diteruskan ke supplier via API → hasil dikirim otomatis → semua uang tercatat.

## 1. Keputusan yang SUDAH DIKUNCI (jangan diubah tanpa izin)

| # | Keputusan | Pilihan |
|---|-----------|---------|
| 1 | Frontend | Terpisah: **Nuxt** (storefront) + **Vue 3 + Vite** (admin) |
| 2 | Integrasi supplier | API resmi utama; **input produk manual** sebagai fallback untuk supplier tanpa API |
| 3 | Deployment | **Satu VPS**, layanan dipisah secara logis (Docker Compose) |
| 4 | Tampilan | **Neon dark** default, light mode opsional (toggle) |
| 5 | Payment gateway | **Satu** gateway dahulu (QRIS + VA + e-wallet). Dibungkus interface agar bisa diganti |
| 6 | Channel MVP | Website saja. Telegram/WhatsApp = fase 2 |

## 2. Tech stack

| Bagian | Teknologi |
|--------|-----------|
| Backend | Laravel (versi stabil terbaru), REST API `/api/v1` |
| DB | MySQL |
| Cache + queue | Redis + Laravel Queue (Horizon bila perlu) |
| Realtime | Laravel Reverb (fase 2) |
| Storefront | Nuxt + Tailwind |
| Admin | Vue 3 + Vite + Tailwind + Pinia + TanStack Query |
| Bot | Telegram Bot API, WhatsApp Business Platform (fase 2) |

## 3. Struktur repo (monorepo)

```
unisell/
├── apps/
│   ├── api/          # Laravel
│   ├── storefront/   # Nuxt
│   └── admin/        # Vue + Vite
├── packages/
│   └── ui/           # token desain neon + komponen bersama
├── docker/
├── docs/
└── AGENTS.md
```

## 4. Aturan yang TIDAK BOLEH dilanggar (invariants)

1. **Satu sistem order** untuk semua channel. Dilarang membuat order terpisah per channel.
2. **Pisahkan** `supplier_products` (data asli supplier) dari `products` (katalog toko). Hubungkan lewat `product_mappings`.
3. **Idempotency**: webhook pembayaran yang datang berulang tidak boleh menyebabkan pembelian ganda (unique key + lock).
4. API key/secret supplier **terenkripsi** di DB dan **tidak pernah** muncul di frontend atau log.
5. Verifikasi **signature** setiap webhook sebelum diproses.
6. Pesanan **hanya dianggap sukses** jika supplier mengonfirmasi sukses. Gagal → retry → antrean cek manual → refund. Jangan pernah otomatis "sukses".
7. Jika langganan supplier berakhir, produk terkunci untuk penjualan baru, tetapi **pesanan yang sedang berjalan tetap diselesaikan** atau di-refund. Data tidak dihapus.
8. Semua nilai uang disimpan sebagai **integer rupiah** (bukan float).
9. Setiap aksi admin penting masuk `audit_logs`.
10. Pencatatan uang dipisah: (a) pembayaran pelanggan → toko, (b) langganan/deposit → supplier.

## 5. Skema database

Gunakan `id` bigint, `created_at`, `updated_at` di semua tabel, kecuali dinyatakan lain.

| Tabel | Kolom kunci |
|-------|-------------|
| users | name, email, password, role_id |
| roles | name (admin, staff, customer) |
| suppliers | name, website, docs_url, auth_method, status, health_status, balance, last_checked_at |
| supplier_credentials | supplier_id, payload (encrypted) |
| supplier_packages | supplier_id, name, price, period_days, product_limit |
| supplier_subscriptions | supplier_id, package_id, status (active/expired/cancelled), starts_at, ends_at |
| supplier_products | supplier_id, external_id, name, cost_price, stock_status, raw (json), synced_at. Unique(supplier_id, external_id) |
| products | category_id, name, slug, description, status |
| product_categories | parent_id, name, slug |
| product_mappings | product_id, supplier_product_id, priority, is_active |
| product_prices | product_id, mode (manual/fixed/percent), markup_value, sell_price |
| orders | order_no (unique), user_id/guest, channel, status, total, customer_input (json) |
| order_items | order_id, product_id, qty, unit_price, unit_cost |
| payment_transactions | order_id, gateway, ref, status, amount, fee, paid_at, expires_at |
| supplier_orders | order_item_id, supplier_id, external_ref, status, attempts, last_error |
| deliveries | order_id, type (code/file/voucher), payload (encrypted), delivered_at |
| refunds | order_id, amount, reason, status |
| notifications | target_type, target_id, channel, type, payload, read_at |
| webhook_logs | source, event_id (unique), payload, signature_valid, processed_at |
| audit_logs | user_id, action, subject_type, subject_id, diff (json) |

## 6. State machine

**Order**
`pending_payment → paid → processing → delivered`
Cabang gagal: `processing → failed_retry → manual_review → refunded`
Lainnya: `pending_payment → expired | cancelled`

**Supplier order**
`queued → sent → success | failed → retry (maks 3, exponential backoff) → manual_review`

## 7. Logika harga

```
laba_bersih = harga_jual - harga_modal - biaya_gateway - alokasi_langganan
```

Tiga mode: **manual**, **markup tetap** (+Rp), **persentase** (+%).
Fitur simulasi: input biaya langganan + laba per transaksi → tampilkan jumlah transaksi untuk balik modal.

## 8. Endpoint API (`/api/v1`)

```
auth:          POST login | POST logout | GET profile
suppliers:     GET index | GET {id} | GET {id}/packages | GET {id}/health
subscriptions: GET index | POST subscribe | POST renew | POST cancel
products:      GET index | GET {slug} | GET categories | GET search
orders:        POST create | GET {order_no} | GET history
payments:      POST create | POST webhook
admin/*:       dashboard, suppliers, products, pricing, orders, reports
integrations:  telegram, whatsapp   (fase 2)
```

Standar: JSON, validasi via FormRequest, rate limit, response error seragam `{ error: { code, message } }`.

## 9. Fase pengerjaan

Setiap fase selesai hanya jika **semua kriteria terpenuhi** dan test lulus.

### Fase 1 — Fondasi
- Setup monorepo, Docker Compose (api, mysql, redis, nginx).
- Laravel + auth (Sanctum), RBAC, semua migrasi di bagian 5.
- Scaffold Nuxt dan Vue admin dengan token desain neon.
- ✅ Selesai bila: `docker compose up` jalan, admin bisa login, migrasi + seeder sukses.

### Fase 2 — Supplier & langganan
- CRUD supplier, kredensial terenkripsi, paket, langganan.
- Adapter supplier: `SupplierDriverInterface` (`syncProducts`, `placeOrder`, `checkOrder`, `checkHealth`, `getBalance`) + satu driver contoh + driver manual.
- Connection checker terjadwal.
- ✅ Selesai bila: aktivasi/penghentian langganan mengunci/membuka produk; kredensial tidak bocor di response.

### Fase 3 — Product aggregator
- Sync produk ke `supplier_products`, pilih produk satu per satu → `products`, mapping, harga/margin, simulasi balik modal.
- ✅ Selesai bila: produk dari supplier tanpa langganan aktif tidak bisa dipublikasikan.

### Fase 4 — Order & pembayaran
- Checkout, payment gateway, webhook (signature + idempotency), supplier order via queue, auto delivery, retry, refund.
- ✅ Selesai bila: test webhook ganda hanya menghasilkan **1** supplier order; supplier gagal masuk retry lalu manual review.

### Fase 5 — Storefront
- Beranda, katalog, kategori, detail, checkout, status pesanan, riwayat, bantuan. Responsif mobile, SEO.
- ✅ Selesai bila: pembeli bisa beli end-to-end tanpa menghubungi admin.

### Fase 6 — Laporan admin
- Dashboard: omzet, modal, biaya langganan, biaya gateway, laba bersih, produk terlaris, supplier terbaik, tingkat gagal. Evaluasi "supplier layak dipertahankan?".

### Fase 7 — Hardening & deploy
- Uji transaksi bersamaan, keamanan, backup DB terjadwal, monitoring, deploy VPS.

### Versi 2 (setelah MVP stabil)
Telegram bot, WhatsApp notifikasi, Reverb realtime, notification center penuh.

## 10. Desain: Neon

**Karakter**: gelap, kontras tinggi, aksen menyala, glow halus. Jangan berlebihan: glow hanya pada elemen interaktif/penting.

```css
:root {
  --bg:        #0A0B14;
  --surface:   #12142A;
  --surface-2: #1A1D3A;
  --border:    #2A2E5A;
  --text:      #E8EAF6;
  --muted:     #8A90B8;
  --primary:   #00F0FF; /* cyan neon */
  --accent:    #FF2BD6; /* magenta neon */
  --success:   #B6FF3B; /* lime neon */
  --warning:   #FFB800;
  --danger:    #FF4D6D;
  --glow-primary: 0 0 12px rgba(0,240,255,.45);
  --glow-accent:  0 0 12px rgba(255,43,214,.45);
  --radius: 12px;
}
```

Aturan UI:
- Font: Inter (teks), JetBrains Mono (angka, kode, nomor order).
- Tombol utama: latar `--primary`, teks gelap, glow saat hover/focus.
- Status memakai warna **dan** label teks (jangan hanya warna).
- Kontras teks minimal WCAG AA. Hormati `prefers-reduced-motion`.
- Light mode: balik token (`--bg` terang, glow dimatikan), toggle di header.
- Semua token di `packages/ui` dan dipakai bersama Nuxt dan admin.

## 11. Keamanan & kualitas

- Rate limiting, CORS ketat, validasi input, proteksi duplicate order.
- Secret hanya di `.env` / secret store. Jangan commit.
- Test wajib: webhook idempotency, state machine order, penguncian langganan, perhitungan laba, enkripsi kredensial.
- Backup DB harian + uji restore.

## 12. Cara agent bekerja

1. Mulai dari **Fase 1**. Jangan lompat fase.
2. Sebelum coding fase baru, tulis rencana singkat (file yang akan dibuat/diubah) lalu tunggu persetujuan.
3. Jangan menambah fitur di luar spesifikasi. Jika ada ambiguitas, **tanya**, jangan menebak.
4. Jangan mengarang endpoint/format API supplier atau gateway. Jika dokumentasi belum ada, buat adapter dengan interface + mock, dan tandai `TODO(docs)`.
5. Setiap fase diakhiri dengan: daftar file berubah, cara menjalankan, hasil test, dan hal yang belum selesai.
6. Catat keputusan teknis baru di `docs/decisions.md`.

## 13. Checklist MVP

- [ ] Admin login & dashboard
- [ ] Manajemen supplier
- [ ] Paket & langganan supplier
- [ ] Sinkronisasi produk
- [ ] Pemilihan produk untuk toko
- [ ] Harga & margin
- [ ] Katalog website
- [ ] Checkout & pembayaran
- [ ] Pengiriman otomatis
- [ ] Riwayat transaksi & laporan
