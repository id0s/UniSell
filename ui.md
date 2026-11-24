Aku justru menyarankan **keduanya: tersedia template warna profesional secara default, tetapi pengguna juga bisa mengustomisasi warna sendiri.**

Jadi UniSell memiliki fitur **Theme & Appearance Manager**, seperti aplikasi SaaS premium.

Tujuannya bukan hanya mengganti warna utama, tetapi mengatur keseluruhan tampilan secara konsisten, termasuk sidebar, tombol, background, border, badge, dan komponen lainnya.

# **1. Sistem tema UniSell**

Aku menyarankan tiga tingkat pengaturan:

Level 1

**Preset Theme**

Pengguna memilih template warna yang sudah dirancang dan dipadukan secara profesional.

## **Emerald**

Professional

## **Ocean**

Corporate

## **Indigo**

Modern

## **Rose**

Elegant

Level 2

**Custom Colors**

Pengguna bebas memilih warna primary, secondary, accent, dan warna pendukung lainnya.

Level 3

**Advanced Theme**

Mengatur komponen, sidebar, radius, density, dan appearance secara lebih detail.

# **2. Pilihan preset palette**

Aku menyarankan minimal 8 preset bawaan.

**Emerald Professional**

Default UniSell

**Ocean Corporate**

Biru profesional

**Indigo Modern**

Teknologi dan SaaS

**Violet Premium**

Elegan dan modern

**Rose Elegant**

Lembut dan premium

**Amber Creative**

Hangat dan kreatif

**Teal Minimal**

Bersih dan tenang

**Slate Neutral**

Minimalis enterprise

Semua preset harus memiliki pasangan Light dan Dark. Jadi satu palette bisa digunakan dalam dua mode tanpa merusak keterbacaan.

# **3. Contoh Theme Customizer**

Berikut gambaran interaksi yang bisa kita implementasikan di dashboard.

**Appearance Settings**

Live Preview

**Color preset**

EmeraldSelected

Ocean

Indigo

Rose

Violet

Teal

**Appearance mode**

LightDarkSystem

**Preview**

## **UniSell**

**Dashboard Overview**

# **Rp12.500.000**

Total Revenue

**+12.8% this month**

**Primary Button**

**Border radius**

SmallMediumLargeXL

**Interface density**

Compact

Comfortable

Spacious

&#x20;Salin konfigurasi preview

Preview interaktif ini merupakan simulasi konfigurasi. Pengaturan belum disimpan ke server.

# **4. Spesifikasi fitur Theme Manager**

Halaman pengaturan tampilan nantinya mempunyai beberapa tab.

| Tab        | Fitur                     |
| ---------- | ------------------------- |
| Presets    | Memilih palette bawaan    |
| Colors     | Mengatur warna custom     |
| Appearance | Light, Dark, System       |
| Layout     | Sidebar, navbar, layout   |
| Components | Radius dan density        |
| Branding   | Logo dan identitas toko   |
| Advanced   | Export/import konfigurasi |

Ada satu fitur tambahan yang menurutku sangat bagus: **Save as Custom Theme**.

Contohnya:

- Emerald Professional (default).
- Ocean Corporate (default).
- My Custom Theme (buatan pengguna).
- Client Branding (tema khusus toko).

Pengguna bisa membuat beberapa tema dan berpindah di antaranya.

# **5. Implementasi teknis Vue**

Gunakan CSS variables supaya perubahan palette langsung memengaruhi seluruh aplikasi.

Contoh:

```
:root {
  --primary: #059669;
  --primary-foreground: #ffffff;
  --accent: #10b981;
  --background: #f8fafc;
  --surface: #ffffff;
  --foreground: #0f172a;
  --border: #e2e8f0;
  --radius: 12px;
}

.dark {
  --primary: #10b981;
  --primary-foreground: #ffffff;
  --accent: #34d399;
  --background: #0b1120;
  --surface: #111827;
  --foreground: #f8fafc;
  --border: #273449;
}
```

Untuk implementasi:

- Pinia menyimpan theme preference.
- CSS variables menerapkan warna.
- Local storage menyimpan pilihan sementara.
- Laravel menyimpan preferensi akun pengguna.
- System mode mengikuti preferensi perangkat.
- Semua halaman menggunakan semantic tokens.

**Jangan mengganti warna dengan mengubah CSS setiap komponen satu per satu.** Gunakan token sebagai satu-satunya sumber warna.

# **6. Fitur lanjutan yang aku rekomendasikan**

Ada perbedaan antara tema dashboard admin dan branding storefront.

Aku menyarankan keduanya dipisahkan:

| Pengaturan     | Admin         | Storefront         |
| -------------- | ------------- | ------------------ |
| Color preset   | Ya            | Ya                 |
| Custom primary | Ya            | Ya                 |
| Light/Dark     | Ya            | Ya                 |
| Logo           | Logo platform | Logo toko          |
| Font           | Global        | Bisa dikustomisasi |
| Radius         | Ya            | Ya                 |
| Layout         | Ya            | Terbatas           |
| Background     | Ya            | Ya                 |

Dengan demikian, kamu bisa memakai dashboard dark mode Emerald, tetapi storefront tetap light mode Ocean atau palette lain.

# **7. Keputusan desain yang aku tetapkan**

## **UniSell Theme System v1.0**

Preset bawaan

**8 palette**

Custom palette

Didukung

Light / Dark / System

Didukung

Custom radius

Didukung

Interface density

Didukung

Save custom themes

Didukung

Theme export/import

Didukung

**Satu rekomendasi penting:** jadikan Theme Manager sebagai fitur inti dari design system sejak awal, bukan fitur tambahan yang baru dibuat belakangan. Dengan begitu, seluruh halaman admin dan storefront langsung mendukung tema tanpa perlu dibangun ulang.
