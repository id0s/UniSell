<script setup lang="ts">
import { onMounted } from 'vue';

const { config, init, toggleMode } = useStorefrontTheme();
const route = useRoute();
const howToBuyOpen = useState<boolean>('howToBuyOpen', () => false);

onMounted(() => {
  init();
});
</script>

<template>
  <div class="min-h-screen flex flex-col pb-24 md:pb-0" style="background: var(--bg); color: var(--text)">
    <!-- Clean Minimalist Top Navbar (No Sidebar) -->
    <header
      class="topbar-surface sticky top-0 z-40 border-b backdrop-blur-md transition-colors"
      style="color: var(--text)"
    >
      <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 sm:h-16 flex items-center justify-between gap-4">
        <NuxtLink to="/" class="flex items-center gap-2.5 no-underline shrink-0">
          <span
            class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold font-mono-code"
            style="background: #141519; color: #FFFFFF"
          >
            us.
          </span>
          <div class="leading-tight">
            <span class="text-sm sm:text-base font-bold tracking-tight block" style="color: var(--text)">
              UniSell Digital
            </span>
            <span class="text-[10px] hidden sm:block" style="color: var(--muted)">
              Toko Digital Otomatis 24/7
            </span>
          </div>
        </NuxtLink>

        <nav class="hidden md:flex items-center gap-7 text-xs font-medium">
          <NuxtLink to="/#katalog" class="no-underline hover:opacity-80" style="color: var(--text)">
            Katalog Produk
          </NuxtLink>
          <button
            type="button"
            class="bg-transparent border-0 p-0 text-xs font-medium cursor-pointer hover:opacity-80"
            style="color: var(--muted)"
            @click="howToBuyOpen = true"
          >
            Cara Belanja
          </button>
          <NuxtLink to="/orders" class="no-underline hover:opacity-80" style="color: var(--muted)">
            Lacak Pesanan
          </NuxtLink>
          <NuxtLink to="/help" class="no-underline hover:opacity-80" style="color: var(--muted)">
            Bantuan
          </NuxtLink>
        </nav>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="w-9 h-9 rounded-xl border flex items-center justify-center cursor-pointer tap-bounce"
            style="border-color: var(--border); color: var(--text); background: var(--surface-2)"
            title="Ganti Tema Terang / Gelap"
            @click="toggleMode"
          >
            <svg v-if="config.mode === 'dark'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
          </button>

          <NuxtLink
            to="/products"
            class="hidden sm:inline-flex px-4 py-2 rounded-xl text-xs font-semibold no-underline transition hover:opacity-90 tap-bounce"
            style="background: var(--lp-accent); color: var(--lp-accent-fg)"
          >
            Semua Produk →
          </NuxtLink>
        </div>
      </div>
    </header>

    <main class="flex-1">
      <NuxtPage />
    </main>

    <footer class="hidden md:block border-t px-6 py-8 mt-8" style="background: var(--bg); border-color: var(--border)">
      <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-4 text-xs" style="color: var(--muted)">
        <div class="flex items-center gap-2.5">
          <span class="w-6 h-6 rounded-lg flex items-center justify-center text-[10px] font-bold font-mono-code text-white" style="background: #141519">
            us.
          </span>
          <span>© 2026 UniSell Digital — Toko Produk Digital Otomatis 24/7</span>
        </div>
        <div class="flex items-center gap-5">
          <button type="button" class="bg-transparent border-0 p-0 text-xs cursor-pointer" style="color: var(--muted)" @click="howToBuyOpen = true">Cara Belanja</button>
          <NuxtLink to="/products" class="no-underline" style="color: var(--muted)">Katalog</NuxtLink>
          <NuxtLink to="/orders" class="no-underline" style="color: var(--muted)">Lacak Pesanan</NuxtLink>
          <NuxtLink to="/help" class="no-underline" style="color: var(--muted)">Bantuan</NuxtLink>
        </div>
      </div>
    </footer>

    <!-- Floating iOS / iPhone Glass Pill Dock (< md) -->
    <div class="md:hidden fixed bottom-4 inset-x-0 z-40 px-4 pointer-events-none">
      <nav
        class="pointer-events-auto max-w-[360px] mx-auto rounded-full border backdrop-blur-xl grid grid-cols-4 p-1.5 shadow-2xl"
        style="
          background: color-mix(in srgb, var(--surface) 82%, transparent);
          border-color: color-mix(in srgb, var(--border) 90%, rgba(255,255,255,0.12));
          box-shadow: 0 12px 32px -6px rgba(0, 0, 0, 0.35), 0 1px 0 rgba(255,255,255,0.08) inset;
        "
      >
        <NuxtLink
          to="/"
          class="flex flex-col items-center justify-center gap-0.5 py-2 rounded-full no-underline text-[10px] font-semibold transition tap-bounce"
          :style="
            route.path === '/'
              ? { background: 'var(--lp-accent-soft)', color: 'var(--lp-accent)' }
              : { color: 'var(--muted)' }
          "
        >
          <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          <span>Beranda</span>
        </NuxtLink>

        <NuxtLink
          to="/products"
          class="flex flex-col items-center justify-center gap-0.5 py-2 rounded-full no-underline text-[10px] font-semibold transition tap-bounce"
          :style="
            route.path.startsWith('/products')
              ? { background: 'var(--lp-accent-soft)', color: 'var(--lp-accent)' }
              : { color: 'var(--muted)' }
          "
        >
          <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
          <span>Katalog</span>
        </NuxtLink>

        <NuxtLink
          to="/orders"
          class="flex flex-col items-center justify-center gap-0.5 py-2 rounded-full no-underline text-[10px] font-semibold transition tap-bounce"
          :style="
            route.path.startsWith('/orders')
              ? { background: 'var(--lp-accent-soft)', color: 'var(--lp-accent)' }
              : { color: 'var(--muted)' }
          "
        >
          <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
          <span>Lacak</span>
        </NuxtLink>

        <button
          type="button"
          class="flex flex-col items-center justify-center gap-0.5 py-2 rounded-full bg-transparent border-0 text-[10px] font-semibold cursor-pointer transition tap-bounce"
          :style="
            howToBuyOpen
              ? { background: 'var(--lp-accent-soft)', color: 'var(--lp-accent)' }
              : { color: 'var(--muted)' }
          "
          @click="howToBuyOpen = true"
        >
          <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
          <span>Panduan</span>
        </button>
      </nav>
    </div>

    <!-- Bounce Popup Modal: Cara Belanja -->
    <div
      v-if="howToBuyOpen"
      class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
      @click.self="howToBuyOpen = false"
    >
      <div
        class="modal-bounce-in w-full max-w-md rounded-3xl border p-6 space-y-5 shadow-2xl"
        style="background: var(--surface); border-color: var(--border); color: var(--text)"
      >
        <div class="flex items-center justify-between gap-3">
          <div>
            <h3 class="text-base font-bold">Cara Belanja di UniSell</h3>
            <p class="text-xs mt-0.5" style="color: var(--muted)">Tanpa daftar akun · Otomatis 24 jam</p>
          </div>
          <button
            type="button"
            class="w-8 h-8 rounded-full border flex items-center justify-center text-sm cursor-pointer tap-bounce"
            style="background: var(--surface-2); border-color: var(--border); color: var(--text)"
            @click="howToBuyOpen = false"
          >
            ✕
          </button>
        </div>

        <div class="space-y-3">
          <div class="flex items-start gap-3 p-3 rounded-2xl border" style="background: var(--surface-2); border-color: var(--border)">
            <span class="w-7 h-7 rounded-xl text-xs font-mono-code font-bold flex items-center justify-center shrink-0" style="background: var(--lp-accent); color: var(--lp-accent-fg)">1</span>
            <div>
              <div class="text-xs font-bold">Pilih Produk Digital</div>
              <p class="text-xs mt-0.5 leading-relaxed" style="color: var(--muted)">Cari pulsa, data, topup game, PLN, atau e-money lalu klik produknya.</p>
            </div>
          </div>

          <div class="flex items-start gap-3 p-3 rounded-2xl border" style="background: var(--surface-2); border-color: var(--border)">
            <span class="w-7 h-7 rounded-xl text-xs font-mono-code font-bold flex items-center justify-center shrink-0" style="background: var(--lp-accent); color: var(--lp-accent-fg)">2</span>
            <div>
              <div class="text-xs font-bold">Masukkan Nomor &amp; Bayar</div>
              <p class="text-xs mt-0.5 leading-relaxed" style="color: var(--muted)">Isi nomor HP / ID tujuan lalu bayar via QRIS, VA, atau e-wallet.</p>
            </div>
          </div>

          <div class="flex items-start gap-3 p-3 rounded-2xl border" style="background: var(--surface-2); border-color: var(--border)">
            <span class="w-7 h-7 rounded-xl text-xs font-mono-code font-bold flex items-center justify-center shrink-0" style="background: #22C55E; color: #FFFFFF">3</span>
            <div>
              <div class="text-xs font-bold">Otomatis Masuk Hitungan Detik</div>
              <p class="text-xs mt-0.5 leading-relaxed" style="color: var(--muted)">Pesanan langsung diproses 24/7 dengan garansi refund bila gagal.</p>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-2.5 rounded-xl text-xs font-semibold cursor-pointer tap-bounce"
          style="background: var(--lp-accent); color: var(--lp-accent-fg)"
          @click="howToBuyOpen = false"
        >
          Mengerti, Mulai Belanja
        </button>
      </div>
    </div>
  </div>
</template>
