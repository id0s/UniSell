<script setup lang="ts">
import { onMounted } from 'vue';

const { config, init, toggleMode } = useStorefrontTheme();
const route = useRoute();

onMounted(() => {
  init();
});

const isHome = computed(() => route.path === '/');
</script>

<template>
  <div class="min-h-screen flex flex-col" style="background: var(--bg); color: var(--text)">
    <!-- Sleek Translucent Navbar (matches reference video top bar) -->
    <header
      class="sticky top-0 z-40 border-b backdrop-blur-md transition-colors"
      :style="
        isHome
          ? {
              background: 'rgba(8, 10, 18, 0.72)',
              borderColor: 'rgba(255, 255, 255, 0.08)',
              color: '#FFFFFF',
            }
          : {
              background: 'color-mix(in srgb, var(--surface) 88%, transparent)',
              borderColor: 'var(--border)',
              color: 'var(--text)',
            }
      "
    >
      <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between gap-4">
        <NuxtLink to="/" class="flex items-center gap-2.5 no-underline">
          <span
            class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold font-mono-code"
            style="background: #2555F4; color: #FFFFFF"
          >
            us.
          </span>
          <span class="text-base font-bold tracking-tight" :style="{ color: isHome ? '#FFFFFF' : 'var(--text)' }">
            UniSell
          </span>
        </NuxtLink>

        <nav class="hidden md:flex items-center gap-7 text-xs font-medium">
          <NuxtLink to="/#arsitektur" class="no-underline hover:opacity-80" :style="{ color: isHome ? '#D4D4D8' : 'var(--muted)' }">
            Platform
          </NuxtLink>
          <NuxtLink to="/#analitik" class="no-underline hover:opacity-80" :style="{ color: isHome ? '#D4D4D8' : 'var(--muted)' }">
            Analitik
          </NuxtLink>
          <NuxtLink to="/products" class="no-underline hover:opacity-80" :style="{ color: isHome ? '#FFFFFF' : 'var(--text)' }">
            Katalog
          </NuxtLink>
          <NuxtLink to="/orders" class="no-underline hover:opacity-80" :style="{ color: isHome ? '#D4D4D8' : 'var(--muted)' }">
            Lacak Pesanan
          </NuxtLink>
          <NuxtLink to="/help" class="no-underline hover:opacity-80" :style="{ color: isHome ? '#D4D4D8' : 'var(--muted)' }">
            Bantuan
          </NuxtLink>
        </nav>

        <div class="flex items-center gap-2.5">
          <button
            type="button"
            class="w-8 h-8 rounded-lg border flex items-center justify-center text-xs cursor-pointer"
            :style="
              isHome
                ? { borderColor: 'rgba(255,255,255,0.16)', color: '#FFFFFF', background: 'rgba(255,255,255,0.06)' }
                : { borderColor: 'var(--border)', color: 'var(--text)', background: 'var(--surface-2)' }
            "
            title="Toggle Dark / Light"
            @click="toggleMode"
          >
            {{ config.mode === 'dark' ? '☀️' : '🌙' }}
          </button>

          <a
            href="/admin/"
            class="px-3.5 py-1.5 rounded-md text-xs font-medium no-underline border transition hover:opacity-90"
            :style="
              isHome
                ? { borderColor: 'rgba(255,255,255,0.2)', color: '#FFFFFF' }
                : { borderColor: 'var(--border)', color: 'var(--text)' }
            "
          >
            Sign in
          </a>

          <NuxtLink
            to="/products"
            class="px-3.5 py-1.5 rounded-md text-xs font-semibold no-underline transition hover:opacity-90"
            :style="
              isHome
                ? { background: '#FFFFFF', color: '#09090B' }
                : { background: '#2555F4', color: '#FFFFFF' }
            "
          >
            Mulai Belanja
          </NuxtLink>
        </div>
      </div>
    </header>

    <main class="flex-1">
      <NuxtPage />
    </main>

    <footer class="border-t px-6 py-12" style="background: var(--surface); border-color: var(--border)">
      <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8 text-xs">
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold font-mono-code text-white" style="background: #2555F4">
              us.
            </span>
            <span class="font-bold text-sm">UniSell Digital</span>
          </div>
          <p style="color: var(--muted)">
            Platform billing &amp; agregator produk digital multi-supplier dengan pengiriman otomatis 24/7.
          </p>
        </div>

        <div class="space-y-2">
          <div class="font-bold">Storefront</div>
          <div class="flex flex-col gap-1.5">
            <NuxtLink to="/products" class="no-underline" style="color: var(--muted)">Katalog Produk</NuxtLink>
            <NuxtLink to="/orders" class="no-underline" style="color: var(--muted)">Lacak Pesanan</NuxtLink>
            <NuxtLink to="/help" class="no-underline" style="color: var(--muted)">Pusat Bantuan</NuxtLink>
          </div>
        </div>

        <div class="space-y-2">
          <div class="font-bold">Jaminan Layanan</div>
          <div class="space-y-1.5" style="color: var(--muted)">
            <div>Pembayaran Terverifikasi Otomatis</div>
            <div>Perlindungan Transaksi Ganda</div>
            <div>Privasi &amp; Data Terlindungi</div>
          </div>
        </div>

        <div class="space-y-2">
          <div class="font-bold">Admin Console</div>
          <div class="flex flex-col gap-1.5">
            <a href="/admin/" class="no-underline" style="color: #2555F4">Masuk Dashboard →</a>
            <span style="color: var(--muted)">Telegram &amp; WhatsApp (Fase 2)</span>
          </div>
        </div>
      </div>
      <div class="max-w-7xl mx-auto mt-8 pt-6 border-t text-center text-[11px]" style="border-color: var(--border); color: var(--muted)">
        © 2026 UniSell Digital. All rights reserved.
      </div>
    </footer>
  </div>
</template>
