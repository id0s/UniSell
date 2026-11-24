<script setup lang="ts">
const config = useRuntimeConfig();
const router = useRouter();
const activeCat = ref('');
const activeDigestIdx = ref(0);
const heroMode = ref<'search' | 'track'>('search');
const heroInput = ref('');
const liveTxCount = ref(14280);

function handleHeroAction() {
  const val = heroInput.value.trim();
  if (heroMode.value === 'track' && val) {
    router.push(`/orders/${encodeURIComponent(val)}`);
    return;
  }
  if (val) {
    router.push(`/products?q=${encodeURIComponent(val)}`);
    return;
  }
  router.push('/products');
}

interface ProductItem {
  id: number;
  name: string;
  slug: string;
  description?: string;
  sell_price: number;
  category?: { name: string; slug: string };
}

interface CategoryItem {
  id: number;
  name: string;
  slug: string;
  products_count: number;
}

const { data: productsResp } = await useFetch<{ data: ProductItem[] }>(
  `${config.public.apiBase}/products`
);
const { data: categoriesResp } = await useFetch<{ data: CategoryItem[] }>(
  `${config.public.apiBase}/products/categories`
);

const filteredProducts = computed(() => {
  const list = productsResp.value?.data || [];
  if (!activeCat.value) return list;
  return list.filter((p) => p.category?.slug === activeCat.value);
});

const digestItems = [
  { title: 'Mobile Legends 86 Diamonds — terkirim', detail: 'Pembayaran QRIS terverifikasi otomatis · diamond langsung masuk dalam 1.8 detik.' },
  { title: 'Canva Pro Team 30 Hari — terkirim', detail: 'Pembayaran Virtual Account lunas · akses aplikasi premium terkirim dalam 2.3 detik.' },
  { title: 'Steam Wallet IDR 60.000 — terkirim', detail: 'Pembayaran E-Wallet berhasil · kode voucher langsung tampil dalam 1.2 detik.' },
];

const dotMatrix = Array.from({ length: 14 }, (_, col) =>
  Array.from({ length: 6 }, (_, row) => ({
    col,
    row,
    active: (col * 3 + row * 7) % 5 !== 0 && row >= (col % 3),
    begin: `${((col + row) % 7) * 0.25}s`,
  }))
).flat();

let digestTimer: ReturnType<typeof setInterval> | null = null;
let countTimer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
  digestTimer = setInterval(() => {
    activeDigestIdx.value = (activeDigestIdx.value + 1) % digestItems.length;
  }, 2400);
  countTimer = setInterval(() => {
    liveTxCount.value += Math.floor(Math.random() * 3) + 1;
  }, 1800);
});
onUnmounted(() => {
  if (digestTimer) clearInterval(digestTimer);
  if (countTimer) clearInterval(countTimer);
});

function rp(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
}
</script>

<template>
  <div class="overflow-x-clip">
    <!-- ========================================================= -->
    <!-- 1. HERO: Diagonal Obsidian Black -> Electric Blue -> Ice  -->
    <!-- ========================================================= -->
    <section class="sentry-hero-bg text-white relative">
      <div class="max-w-7xl mx-auto px-6 pt-16 pb-20 lg:pt-24 lg:pb-24">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
          <!-- Left Copy + Interactive Pill -->
          <div class="lg:col-span-7 space-y-7">
            <div class="anim-fade-up flex flex-wrap items-center gap-2.5 text-[11px] font-mono-code tracking-wider">
              <span class="px-2.5 py-1 rounded border border-white/15 bg-white/5 text-zinc-300">
                AMAN &amp; TERPERCAYA
              </span>
              <span class="px-2.5 py-1 rounded border border-white/15 bg-white/5 text-zinc-300 flex items-center gap-1.5">
                STATUS: <span class="w-2 h-2 rounded-full bg-emerald-400 anim-pulse-blue" /> ONLINE 24/7
              </span>
            </div>

            <h1 class="anim-fade-up anim-d1 text-4xl sm:text-5xl lg:text-[62px] font-semibold tracking-tight leading-[1.06]">
              <span class="text-zinc-400 block">Diproses otomatis</span>
              <span class="text-white block">sebelum pelanggan menunggu</span>
            </h1>

            <p class="anim-fade-up anim-d2 text-sm sm:text-base text-zinc-300 max-w-lg leading-relaxed">
              Belanja topup game, voucher digital, dan aplikasi premium lebih cepat. Bayar lewat QRIS, Virtual Account, atau e-wallet — pesanan langsung terkirim dalam hitungan detik.
            </p>

            <!-- Interactive Pill Input (clean white in light mode, matte charcoal in dark mode) -->
            <form
              class="anim-fade-up anim-d3 max-w-lg rounded-full p-1.5 pl-3 flex items-center gap-2 shadow-2xl border"
              style="background: var(--surface); border-color: var(--border); color: var(--text)"
              @submit.prevent="handleHeroAction"
            >
              <button
                type="button"
                class="px-3 py-1.5 rounded-full text-[11px] font-bold shrink-0 cursor-pointer transition"
                style="background: var(--surface-2); color: var(--text)"
                @click="heroMode = heroMode === 'search' ? 'track' : 'search'"
              >
                {{ heroMode === 'search' ? '🛍️ Katalog' : '📦 Lacak' }}
              </button>

              <input
                v-model="heroInput"
                type="text"
                class="flex-1 bg-transparent border-0 outline-none text-xs sm:text-sm px-1"
                style="color: var(--text)"
                :placeholder="heroMode === 'search' ? 'Cari voucher, topup game, lisensi...' : 'Masukkan nomor order (US-...)'"
              />

              <button
                type="submit"
                class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0 cursor-pointer transition hover:scale-105 shadow-md"
                style="background: var(--lp-accent); color: var(--lp-accent-fg)"
              >
                →
              </button>
            </form>

            <div class="anim-fade-up anim-d4 flex flex-wrap items-center gap-4 text-xs text-zinc-300">
              <NuxtLink to="/products" class="text-white font-semibold underline underline-offset-4">
                Lihat semua produk →
              </NuxtLink>
              <a href="/admin/" class="text-zinc-300 hover:text-white no-underline">
                Masuk Admin Console
              </a>
            </div>
          </div>

          <!-- Right: 100% Native SVG SMIL Animated 3D Conveyor -> Storefront -> Rolling Rp Coins -->
          <div class="lg:col-span-5 flex items-center justify-center">
            <svg viewBox="0 0 440 350" class="w-full max-w-[460px] overflow-visible">
              <defs>
                <linearGradient id="hubRoof" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FFFFFF" />
                  <stop offset="100%" stop-color="#DBEAFE" />
                </linearGradient>
                <linearGradient id="hubLeft" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#E2E8F0" />
                  <stop offset="100%" stop-color="#94A3B8" />
                </linearGradient>
                <linearGradient id="hubRight" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#F8FAFC" />
                  <stop offset="100%" stop-color="#CBD5E1" />
                </linearGradient>
                <linearGradient id="boxTop" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FDE68A" />
                  <stop offset="100%" stop-color="#F59E0B" />
                </linearGradient>
                <linearGradient id="coinGrad" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FFFFFF" />
                  <stop offset="100%" stop-color="#93C5FD" />
                </linearGradient>
              </defs>

              <!-- Isometric Floor Grid -->
              <g stroke="rgba(255,255,255,0.14)" stroke-width="1">
                <line x1="30" y1="150" x2="310" y2="312" />
                <line x1="80" y1="110" x2="360" y2="272" />
                <line x1="60" y1="240" x2="300" y2="100" />
                <line x1="140" y1="290" x2="380" y2="150" />
              </g>

              <!-- Moving Conveyor Belt Track -->
              <polygon
                points="36,105 196,197 166,215 6,123"
                fill="rgba(255,255,255,0.14)"
                stroke="rgba(255,255,255,0.4)"
                stroke-width="1"
              />
              <line x1="22" y1="114" x2="180" y2="205" stroke="#FFFFFF" stroke-width="2" stroke-dasharray="8 8" opacity="0.65">
                <animate attributeName="stroke-dashoffset" from="32" to="0" dur="1.1s" repeatCount="indefinite" />
              </line>

              <!-- Supplier Box 1 sliding continuously on conveyor -->
              <g>
                <animateTransform attributeName="transform" type="translate" values="-70,-40; 45,26" dur="3.2s" repeatCount="indefinite" />
                <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;0.15;0.82;1" dur="3.2s" repeatCount="indefinite" />
                <polygon points="118,122 146,138 118,154 90,138" fill="url(#boxTop)" />
                <polygon points="90,138 118,154 118,180 90,164" fill="#D97706" />
                <polygon points="146,138 118,154 118,180 146,164" fill="#B45309" />
              </g>

              <!-- Supplier Box 2 (offset 1.6s) -->
              <g opacity="0">
                <animateTransform attributeName="transform" type="translate" values="-70,-40; 45,26" dur="3.2s" begin="1.6s" repeatCount="indefinite" />
                <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;0.15;0.82;1" dur="3.2s" begin="1.6s" repeatCount="indefinite" />
                <polygon points="118,122 146,138 118,154 90,138" fill="url(#boxTop)" />
                <polygon points="90,138 118,154 118,180 90,164" fill="#D97706" />
                <polygon points="146,138 118,154 118,180 146,164" fill="#B45309" />
              </g>

              <!-- Gently Floating UniSell Isometric Storefront Hub -->
              <g>
                <animateTransform attributeName="transform" type="translate" values="0,0; 0,-9; 0,0" dur="3.6s" repeatCount="indefinite" />
                <polygon points="245,98 338,152 245,206 152,152" fill="url(#hubRoof)" />
                <polygon points="152,152 245,206 245,282 152,228" fill="url(#hubLeft)" />
                <polygon points="176,184 212,205 212,248 176,227" fill="#EC4899" opacity="0.88">
                  <animate attributeName="opacity" values="0.65;1;0.65" dur="2.2s" repeatCount="indefinite" />
                </polygon>
                <polygon points="338,152 245,206 245,282 338,228" fill="url(#hubRight)" />
                <polygon points="262,188 328,150 328,170 262,208" fill="#18181B" />
                <polygon points="260,206 330,166 344,178 274,218" fill="#2555F4" />
                <polygon points="282,216 312,199 312,243 282,260" fill="#1E1B4B" />
              </g>

              <!-- Rolling Rp Profit Coin 1 -->
              <g>
                <animateTransform attributeName="transform" type="translate" values="0,0; 78,44" dur="2.6s" repeatCount="indefinite" />
                <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;0.15;0.82;1" dur="2.6s" repeatCount="indefinite" />
                <ellipse cx="318" cy="244" rx="15" ry="19" fill="url(#coinGrad)" stroke="#2555F4" stroke-width="2" />
                <text x="318" y="248" text-anchor="middle" fill="#1E1B4B" font-size="10.5" font-family="JetBrains Mono, monospace" font-weight="800">
                  Rp
                </text>
              </g>

              <!-- Rolling Rp Profit Coin 2 (offset 1.3s) -->
              <g opacity="0">
                <animateTransform attributeName="transform" type="translate" values="0,0; 78,44" dur="2.6s" begin="1.3s" repeatCount="indefinite" />
                <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;0.15;0.82;1" dur="2.6s" begin="1.3s" repeatCount="indefinite" />
                <ellipse cx="318" cy="244" rx="15" ry="19" fill="url(#coinGrad)" stroke="#EC4899" stroke-width="2" />
                <text x="318" y="248" text-anchor="middle" fill="#1E1B4B" font-size="10.5" font-family="JetBrains Mono, monospace" font-weight="800">
                  Rp
                </text>
              </g>

              <text x="80" y="92" fill="#BFDBFE" font-size="9.5" font-family="JetBrains Mono, monospace">
                PRODUK RESMI MASUK →
              </text>
              <text x="318" y="322" fill="#FFFFFF" font-size="9.5" font-family="JetBrains Mono, monospace" font-weight="600">
                KIRIM INSTAN 24 JAM
              </text>
            </svg>
          </div>
        </div>

        <!-- Trusted / Channel Strip -->
        <div class="mt-16 pt-8 border-t border-white/15 flex flex-wrap items-center justify-between gap-6">
          <div class="text-[10px] font-mono-code uppercase tracking-widest text-white/70">
            METODE PEMBAYARAN &amp; LAYANAN
          </div>
          <div class="flex flex-wrap items-center gap-8 text-sm font-bold tracking-tight text-white/90">
            <span>▲ QRIS</span>
            <span>◈ Virtual Account</span>
            <span>● E-Wallet</span>
            <span>⚡ Kirim Instan</span>
            <span>✓ Garansi Refund</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================================= -->
    <!-- 2. 4-COLUMN ANIMATED ISOMETRIC ARCHITECTURE SECTION       -->
    <!-- ========================================================= -->
    <section id="arsitektur" class="sentry-mist-top py-20">
      <div class="max-w-7xl mx-auto px-6 space-y-12">
        <div class="max-w-2xl space-y-3">
          <h2 class="text-3xl md:text-5xl font-semibold tracking-tight leading-[1.1]">
            <span class="block">Dibangun untuk transaksi</span>
            <span class="block" style="color: var(--lp-accent)">yang benar-benar instan</span>
          </h2>
          <p class="text-sm" style="color: var(--muted)">
            UniSell memangkas proses manual sehingga setiap pesanan dari pembayaran sampai pengiriman selesai dalam hitungan detik.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 border-t border-b" style="border-color: var(--border)">
          <!-- Col 1 -->
          <div class="p-6 lg:border-r space-y-6 flex flex-col justify-between" style="border-color: var(--border)">
            <div class="text-[10px] font-mono-code uppercase tracking-widest" style="color: var(--muted)">01 · OTOMATIS</div>
            <svg viewBox="0 0 180 140" class="w-44 h-32 mx-auto overflow-visible">
              <g>
                <animateTransform attributeName="transform" type="translate" values="0,0; 0,-4; 0,0" dur="3.4s" repeatCount="indefinite" />
                <polygon points="90,82 145,108 90,134 35,108" fill="none" stroke="var(--muted)" stroke-width="1.2" opacity="0.5" />
              </g>
              <g>
                <animateTransform attributeName="transform" type="translate" values="0,0; 0,-10; 0,0" dur="3.4s" repeatCount="indefinite" />
                <polygon points="90,56 145,82 90,108 35,82" fill="none" stroke="var(--muted)" stroke-width="1.2" opacity="0.75" />
              </g>
              <g>
                <animateTransform attributeName="transform" type="translate" values="0,0; 0,-16; 0,0" dur="3.4s" repeatCount="indefinite" />
                <polygon points="90,30 145,56 90,82 35,56" fill="rgba(37,85,244,0.12)" stroke="#2555F4" stroke-width="1.6" />
              </g>
            </svg>
            <div class="space-y-1.5">
              <h3 class="text-sm font-bold">Belanja tanpa antre</h3>
              <p class="text-xs leading-relaxed" style="color: var(--muted)">
                Pesan kapan saja siang atau malam tanpa perlu menunggu balasan admin secara manual.
              </p>
            </div>
          </div>

          <!-- Col 2 -->
          <div class="p-6 lg:border-r space-y-6 flex flex-col justify-between" style="border-color: var(--border)">
            <div class="text-[10px] font-mono-code uppercase tracking-widest" style="color: var(--muted)">02 · AMAN</div>
            <svg viewBox="0 0 180 140" class="w-44 h-32 mx-auto overflow-visible">
              <polygon points="90,68 145,95 90,122 35,95" fill="none" stroke="var(--muted)" stroke-width="1.2" opacity="0.5" />
              <line x1="90" y1="24" x2="90" y2="95" stroke="#2555F4" stroke-width="1.5" stroke-dasharray="4 4">
                <animate attributeName="stroke-dashoffset" from="16" to="0" dur="1s" repeatCount="indefinite" />
              </line>
              <circle cx="90" cy="48" r="14" fill="rgba(37,85,244,0.12)" stroke="#2555F4" stroke-width="1.5">
                <animate attributeName="r" values="12;17;12" dur="2.2s" repeatCount="indefinite" />
              </circle>
              <circle cx="90" cy="48" r="4" fill="#2555F4" />
            </svg>
            <div class="space-y-1.5">
              <h3 class="text-sm font-bold">Pembayaran terverifikasi</h3>
              <p class="text-xs leading-relaxed" style="color: var(--muted)">
                Sistem memastikan setiap pembayaran valid dan terlindungi dari transaksi ganda.
              </p>
            </div>
          </div>

          <!-- Col 3 -->
          <div class="p-6 lg:border-r space-y-6 flex flex-col justify-between" style="border-color: var(--border)">
            <div class="text-[10px] font-mono-code uppercase tracking-widest" style="color: var(--muted)">03 · LENGKAP</div>
            <svg viewBox="0 0 180 140" class="w-44 h-32 mx-auto overflow-visible">
              <g stroke="var(--muted)" stroke-width="1" fill="rgba(37,85,244,0.08)">
                <animateTransform attributeName="transform" type="translate" values="0,0; 0,-8; 0,0" dur="3s" repeatCount="indefinite" />
                <polygon points="60,26 78,35 60,44 42,35" />
                <polygon points="90,14 108,23 90,32 72,23" />
                <polygon points="120,26 138,35 120,44 102,35" />
                <polygon points="90,38 108,47 90,56 72,47" stroke="#2555F4" fill="rgba(37,85,244,0.22)" />
              </g>
              <line x1="90" y1="56" x2="90" y2="88" stroke="#2555F4" stroke-width="1.5" stroke-dasharray="4 3">
                <animate attributeName="stroke-dashoffset" from="14" to="0" dur="0.9s" repeatCount="indefinite" />
              </line>
              <polygon points="90,88 130,108 90,128 50,108" fill="rgba(37,85,244,0.12)" stroke="#2555F4" stroke-width="1.5" />
            </svg>
            <div class="space-y-1.5">
              <h3 class="text-sm font-bold">Banyak pilihan produk</h3>
              <p class="text-xs leading-relaxed" style="color: var(--muted)">
                Topup game, voucher digital, hingga aplikasi premium dari supplier resmi yang selalu aktif.
              </p>
            </div>
          </div>

          <!-- Col 4 -->
          <div class="p-6 space-y-6 flex flex-col justify-between">
            <div class="text-[10px] font-mono-code uppercase tracking-widest" style="color: var(--muted)">04 · TRANSPARAN</div>
            <svg viewBox="0 0 180 140" class="w-44 h-32 mx-auto overflow-visible">
              <polygon points="90,86 142,110 90,134 38,110" fill="none" stroke="var(--muted)" stroke-width="1.2" opacity="0.5" />
              <g>
                <animateTransform attributeName="transform" type="translate" values="0,0; 0,-12; 0,0" dur="3.2s" repeatCount="indefinite" />
                <polygon points="90,30 124,47 90,64 56,47" fill="rgba(37,85,244,0.22)" stroke="#2555F4" stroke-width="1.5" />
                <polygon points="56,47 90,64 90,98 56,81" fill="rgba(37,85,244,0.1)" stroke="#2555F4" stroke-width="1.2" />
                <polygon points="124,47 90,64 90,98 124,81" fill="rgba(37,85,244,0.16)" stroke="#2555F4" stroke-width="1.2" />
              </g>
            </svg>
            <div class="space-y-1.5">
              <h3 class="text-sm font-bold">Harga jelas &amp; garansi</h3>
              <p class="text-xs leading-relaxed" style="color: var(--muted)">
                Harga transparan dalam Rupiah dan garansi pengembalian dana penuh bila pesanan gagal.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================================= -->
    <!-- 3. ANIMATED ANALYTICS & LIVE DIGEST                       -->
    <!-- ========================================================= -->
    <section id="analitik" class="py-20">
      <div class="max-w-7xl mx-auto px-6 space-y-10">
        <div class="max-w-2xl space-y-2">
          <h2 class="text-3xl md:text-5xl font-semibold tracking-tight leading-[1.1]">
            <span class="block">Semua transaksi &amp; margin</span>
            <span class="block" style="color: var(--lp-accent)">terpantau otomatis</span>
          </h2>
          <p class="text-sm" style="color: var(--muted)">
            Setiap pembayaran masuk langsung diverifikasi, diteruskan ke supplier, dan tercatat ke pembukuan tanpa input manual.
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
          <!-- Card 1: Live Animated Dot-Matrix -->
          <div class="lg:col-span-4 rounded-2xl border p-5 flex flex-col justify-between space-y-4 card-hover" style="background: var(--surface); border-color: var(--border)">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold">Transaksi per Jam</span>
              <span class="text-[10px] font-mono-code" style="color: var(--lp-accent)">● Live Matrix</span>
            </div>
            <div class="flex gap-6">
              <div>
                <div class="text-2xl font-bold font-mono-code">{{ liveTxCount.toLocaleString('id-ID') }}</div>
                <div class="text-[11px]" style="color: var(--muted)">Total Delivered</div>
              </div>
              <div>
                <div class="text-2xl font-bold font-mono-code" style="color: var(--lp-accent)">99.8%</div>
                <div class="text-[11px]" style="color: var(--muted)">Success Rate</div>
              </div>
            </div>
            <svg viewBox="0 0 280 120" class="w-full h-28">
              <circle
                v-for="d in dotMatrix"
                :key="`${d.col}-${d.row}`"
                :cx="12 + d.col * 19.5"
                :cy="14 + d.row * 18"
                r="4"
                :fill="d.active ? 'var(--lp-dot)' : 'var(--border)'"
                :opacity="d.active ? 0.9 : 0.4"
              >
                <animate
                  v-if="d.active"
                  attributeName="opacity"
                  values="0.2;1;0.2"
                  dur="2.2s"
                  :begin="d.begin"
                  repeatCount="indefinite"
                />
              </circle>
            </svg>
          </div>

          <!-- Card 2: Animated Funnel Ribbon Wave -->
          <div class="lg:col-span-4 rounded-2xl border p-5 flex flex-col justify-between space-y-4 card-hover" style="background: var(--surface); border-color: var(--border)">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold">Funnel Settlement</span>
              <span class="text-[10px] font-mono-code" style="color: var(--muted)">Avg 2.1s</span>
            </div>
            <div class="flex justify-between text-xs font-mono-code">
              <div>
                <div class="text-[10px]" style="color: var(--muted)">Paid</div>
                <div class="text-base font-bold">100%</div>
              </div>
              <div>
                <div class="text-[10px]" style="color: var(--muted)">Verified</div>
                <div class="text-base font-bold" style="color: #2555F4">99.9%</div>
              </div>
              <div>
                <div class="text-[10px]" style="color: var(--muted)">Delivered</div>
                <div class="text-base font-bold" style="color: #22C55E">99.8%</div>
              </div>
            </div>
            <svg viewBox="0 0 280 110" class="w-full h-28 overflow-visible">
              <defs>
                <linearGradient id="ribbonGrad" x1="0" y1="0" x2="1" y2="0">
                  <stop offset="0%" stop-color="#93C5FD" stop-opacity="0.45" />
                  <stop offset="50%" stop-color="#2555F4" stop-opacity="0.75" />
                  <stop offset="100%" stop-color="#3B82F6" stop-opacity="0.35" />
                </linearGradient>
              </defs>
              <path
                d="M 8 55 C 55 20, 95 28, 140 16 C 185 28, 225 32, 272 55 C 225 78, 185 82, 140 94 C 95 82, 55 90, 8 55 Z"
                fill="url(#ribbonGrad)"
              />
              <!-- Flowing energy signal dot along funnel -->
              <circle r="5" fill="#FFFFFF" stroke="#2555F4" stroke-width="2">
                <animateMotion dur="2.2s" repeatCount="indefinite" path="M 8 55 Q 140 18 272 55" />
              </circle>
              <circle cx="65" cy="55" r="14" fill="var(--surface)" stroke="#2555F4" stroke-width="1.5" />
              <text x="65" y="58" text-anchor="middle" font-size="8" font-family="JetBrains Mono" fill="var(--text)">QRIS</text>
              <circle cx="140" cy="55" r="16" fill="#2555F4">
                <animate attributeName="r" values="15;18;15" dur="1.8s" repeatCount="indefinite" />
              </circle>
              <text x="140" y="58" text-anchor="middle" font-size="8" font-family="JetBrains Mono" fill="#FFFFFF">API</text>
              <circle cx="215" cy="55" r="14" fill="var(--surface)" stroke="#22C55E" stroke-width="1.5" />
              <text x="215" y="58" text-anchor="middle" font-size="8" font-family="JetBrains Mono" fill="var(--text)">SENT</text>
            </svg>
          </div>

          <!-- Card 3: Floating Live Delivery Digest -->
          <div class="lg:col-span-4 rounded-2xl border p-5 space-y-4 shadow-lg card-hover" style="background: var(--surface); border-color: var(--border)">
            <div class="flex items-center justify-between border-b pb-3" style="border-color: var(--border)">
              <span class="text-sm font-bold">Live Delivery Digest</span>
              <span class="text-[10px] font-mono-code px-2 py-0.5 rounded" style="background: var(--surface-2); color: var(--lp-accent)">
                REALTIME
              </span>
            </div>

            <div class="space-y-3">
              <div
                v-for="(d, idx) in digestItems"
                :key="d.title"
                class="p-3 rounded-xl border transition-all duration-300"
                :style="
                  activeDigestIdx === idx
                    ? { background: 'var(--surface-2)', borderColor: 'var(--lp-accent)', transform: 'translateX(4px)' }
                    : { background: 'transparent', borderColor: 'var(--border)', opacity: 0.72 }
                "
              >
                <div class="flex items-center gap-2 text-xs font-bold">
                  <span class="w-1.5 h-1.5 rounded-full" :style="{ background: activeDigestIdx === idx ? '#22C55E' : 'var(--muted)' }" />
                  <span class="truncate">{{ d.title }}</span>
                </div>
                <p class="text-[11px] mt-1 leading-relaxed" style="color: var(--muted)">
                  {{ d.detail }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================================= -->
    <!-- 4. LIVE KATALOG PRODUK INSTAN                             -->
    <!-- ========================================================= -->
    <section class="py-16 border-t" style="border-color: var(--border)">
      <div class="max-w-7xl mx-auto px-6 space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <span class="text-xs font-mono-code uppercase" style="color: var(--lp-accent)">Katalog Live</span>
            <h2 class="text-2xl md:text-3xl font-bold mt-1">Beli Produk Digital Sekarang</h2>
          </div>

          <div class="flex flex-wrap gap-1.5">
            <button
              type="button"
              class="px-3.5 py-1.5 rounded-md text-xs font-semibold border transition cursor-pointer"
              :style="
                activeCat === ''
                  ? { background: 'var(--lp-accent)', color: 'var(--lp-accent-fg)', borderColor: 'var(--lp-accent)' }
                  : { background: 'var(--surface)', color: 'var(--muted)', borderColor: 'var(--border)' }
              "
              @click="activeCat = ''"
            >
              Semua
            </button>
            <button
              v-for="cat in categoriesResp?.data || []"
              :key="cat.id"
              type="button"
              class="px-3.5 py-1.5 rounded-md text-xs font-semibold border transition cursor-pointer"
              :style="
                activeCat === cat.slug
                  ? { background: 'var(--lp-accent)', color: 'var(--lp-accent-fg)', borderColor: 'var(--lp-accent)' }
                  : { background: 'var(--surface)', color: 'var(--muted)', borderColor: 'var(--border)' }
              "
              @click="activeCat = cat.slug"
            >
              {{ cat.name }} ({{ cat.products_count }})
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <NuxtLink
            v-for="product in filteredProducts"
            :key="product.id"
            :to="`/products/${product.slug}`"
            class="p-6 rounded-2xl border flex flex-col justify-between gap-4 no-underline card-hover"
            style="background: var(--surface); border-color: var(--border)"
          >
            <div class="space-y-2.5">
              <div class="flex items-center justify-between">
                <span
                  v-if="product.category"
                  class="px-2.5 py-1 rounded text-[11px] font-semibold"
                  style="background: var(--surface-2); color: var(--lp-accent)"
                >
                  {{ product.category.name }}
                </span>
                <span class="text-[11px] font-mono-code text-emerald-500">● Instan</span>
              </div>
              <h3 class="text-base font-bold" style="color: var(--text)">{{ product.name }}</h3>
              <p class="text-xs line-clamp-2" style="color: var(--muted)">
                {{ product.description }}
              </p>
            </div>
            <div class="flex items-center justify-between pt-4 border-t" style="border-color: var(--border)">
              <span class="text-lg font-bold font-mono-code" style="color: var(--text)">
                {{ rp(product.sell_price) }}
              </span>
              <span class="px-3.5 py-1.5 rounded-md text-xs font-semibold" style="background: var(--lp-accent); color: var(--lp-accent-fg)">
                Beli →
              </span>
            </div>
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
