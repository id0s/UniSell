<script setup lang="ts">
const config = useRuntimeConfig();
const router = useRouter();
const heroInput = ref('');
const howToBuyOpen = useState<boolean>('howToBuyOpen', () => false);

function handleHeroSearch() {
  const val = heroInput.value.trim();
  if (val) {
    router.push(`/products?q=${encodeURIComponent(val)}`);
    return;
  }
  router.push('/products');
}

interface CategoryItem {
  id: number;
  name: string;
  slug: string;
  products_count: number;
}

const { data: categoriesResp } = await useFetch<{ data: CategoryItem[] }>(
  `${config.public.apiBase}/products/categories`
);

const activeCategories = computed(() =>
  (categoriesResp.value?.data || [])
    .filter((c) => c.products_count > 0)
    .sort((a, b) => b.products_count - a.products_count)
);

const totalProducts = computed(() =>
  activeCategories.value.reduce((acc, c) => acc + c.products_count, 0)
);

function categoryIconKey(name: string): string {
  const n = name.toLowerCase();
  if (n.includes('pulsa')) return 'phone';
  if (n.includes('data')) return 'wifi';
  if (n.includes('game')) return 'game';
  if (n.includes('e-money') || n.includes('emoney')) return 'wallet';
  if (n.includes('pln') || n.includes('listrik')) return 'zap';
  if (n.includes('voucher')) return 'ticket';
  if (n.includes('tv')) return 'tv';
  if (n.includes('sms') || n.includes('telpon')) return 'call';
  if (n.includes('aplikasi')) return 'layers';
  if (n.includes('gas')) return 'flame';
  return 'bag';
}

const paymentChannels = [
  { id: 'mandiri', label: "Livin' Mandiri", logo: '/logos/mandiri.svg' },
  { id: 'bni', label: 'wondr BNI', logo: '/logos/wondr.svg' },
  { id: 'dana', label: 'DANA', logo: '/logos/dana.svg' },
  { id: 'ovo', label: 'OVO', logo: '/logos/ovo.svg' },
  { id: 'bca', label: 'BCA Mobile', logo: '/logos/bca.svg' },
  { id: 'bri', label: 'BRImo', logo: '/logos/bri.svg' },
  { id: 'gopay', label: 'GoPay', logo: '/logos/gopay.svg' },
  { id: 'qris', label: 'QRIS', logo: '/logos/qris.svg' },
];
</script>

<template>
  <div class="overflow-x-clip pb-16">
    <!-- ========================================================= -->
    <!-- 1. SEAMLESS HERO (FOKUS PENJELASAN & CTA KE KATALOG)      -->
    <!-- ========================================================= -->
    <section class="sentry-hero-bg" style="color: var(--text)">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 sm:py-14 lg:py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
          <div class="lg:col-span-8 space-y-5">
            <div class="flex flex-wrap items-center gap-2 text-[11px] font-mono-code">
              <span
                class="px-2.5 py-1 rounded-full border flex items-center gap-1.5"
                style="background: var(--surface); border-color: var(--border); color: var(--text)"
              >
                <span class="w-2 h-2 rounded-full bg-emerald-500" /> BUKA 24 JAM · OTOMATIS
              </span>
              <span
                class="px-2.5 py-1 rounded-full border"
                style="background: var(--surface-2); border-color: var(--border); color: var(--muted)"
              >
                TANPA DAFTAR AKUN
              </span>
            </div>

            <h1 class="text-3xl sm:text-4xl lg:text-[48px] font-bold tracking-tight leading-[1.12]">
              Topup Game, Pulsa &amp; Voucher Digital Otomatis
            </h1>

            <p class="text-sm sm:text-base max-w-2xl leading-[1.75]" style="color: var(--muted)">
              <strong style="color: var(--text)">UniSell Digital</strong> adalah toko produk digital serba otomatis. Beli pulsa, paket data, token PLN, saldo e-money, hingga topup game — bayar lewat QRIS, Virtual Account, atau e-wallet, pesanan langsung masuk dalam hitungan detik.
            </p>

            <!-- Primary CTA Buttons -->
            <div class="flex flex-wrap items-center gap-2.5 pt-1">
              <NuxtLink
                to="/products"
                class="px-6 py-3 rounded-xl text-sm font-semibold no-underline transition hover:opacity-90 tap-bounce inline-flex items-center gap-2"
                style="background: var(--lp-accent); color: var(--lp-accent-fg)"
              >
                Buka Katalog ({{ totalProducts }} Produk) →
              </NuxtLink>

              <NuxtLink
                to="/orders"
                class="px-5 py-3 rounded-xl text-xs sm:text-sm font-semibold no-underline border transition inline-flex items-center gap-1.5 card-hover tap-bounce"
                style="background: var(--surface); border-color: var(--border); color: var(--text)"
              >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                Lacak Pesanan
              </NuxtLink>

              <button
                type="button"
                class="px-4 py-3 rounded-xl text-xs sm:text-sm font-semibold border transition cursor-pointer tap-bounce"
                style="background: var(--surface-2); border-color: var(--border); color: var(--text)"
                @click="howToBuyOpen = true"
              >
                Cara Belanja
              </button>
            </div>

            <!-- Quick Search -> goes directly to /products?q=... -->
            <form
              class="max-w-xl rounded-xl p-1 pl-3.5 flex items-center gap-2 border"
              style="background: var(--surface); border-color: var(--border); color: var(--text)"
              @submit.prevent="handleHeroSearch"
            >
              <svg class="w-4 h-4 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input
                v-model="heroInput"
                type="text"
                class="flex-1 bg-transparent border-0 outline-none text-sm py-2"
                style="color: var(--text)"
                placeholder="Cari produk di katalog (Telkomsel, PLN, MLBB, ShopeePay...)"
              />
              <button
                type="submit"
                class="px-4 py-2 rounded-lg font-semibold text-xs shrink-0 cursor-pointer transition hover:opacity-90 tap-bounce"
                style="background: var(--lp-accent); color: var(--lp-accent-fg)"
              >
                Cari
              </button>
            </form>
          </div>

          <!-- Right: 3D Isometric SVG (Desktop) -->
          <div class="flex lg:col-span-4 items-center justify-center pt-2 lg:pt-0">
            <svg viewBox="0 0 440 340" class="w-full max-w-[250px] sm:max-w-[290px] lg:max-w-[320px] overflow-visible">
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

              <g stroke="var(--border)" stroke-width="1">
                <line x1="30" y1="150" x2="310" y2="312" />
                <line x1="80" y1="110" x2="360" y2="272" />
                <line x1="60" y1="240" x2="300" y2="100" />
                <line x1="140" y1="290" x2="380" y2="150" />
              </g>

              <polygon points="36,105 196,197 166,215 6,123" fill="var(--surface-2)" stroke="var(--border)" stroke-width="1" />
              <line x1="22" y1="114" x2="180" y2="205" stroke="var(--lp-accent)" stroke-width="2" stroke-dasharray="8 8" opacity="0.75">
                <animate attributeName="stroke-dashoffset" from="32" to="0" dur="1.1s" repeatCount="indefinite" />
              </line>

              <g>
                <animateTransform attributeName="transform" type="translate" values="-70,-40; 45,26" dur="3.2s" repeatCount="indefinite" />
                <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;0.15;0.82;1" dur="3.2s" repeatCount="indefinite" />
                <polygon points="118,122 146,138 118,154 90,138" fill="url(#boxTop)" />
                <polygon points="90,138 118,154 118,180 90,164" fill="#D97706" />
                <polygon points="146,138 118,154 118,180 146,164" fill="#B45309" />
              </g>

              <g opacity="0">
                <animateTransform attributeName="transform" type="translate" values="-70,-40; 45,26" dur="3.2s" begin="1.6s" repeatCount="indefinite" />
                <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;0.15;0.82;1" dur="3.2s" begin="1.6s" repeatCount="indefinite" />
                <polygon points="118,122 146,138 118,154 90,138" fill="url(#boxTop)" />
                <polygon points="90,138 118,154 118,180 90,164" fill="#D97706" />
                <polygon points="146,138 118,154 118,180 146,164" fill="#B45309" />
              </g>

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

              <g>
                <animateTransform attributeName="transform" type="translate" values="0,0; 78,44" dur="2.6s" repeatCount="indefinite" />
                <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;0.15;0.82;1" dur="2.6s" repeatCount="indefinite" />
                <ellipse cx="318" cy="244" rx="15" ry="19" fill="url(#coinGrad)" stroke="#2555F4" stroke-width="2" />
                <text x="318" y="248" text-anchor="middle" fill="#1E1B4B" font-size="10.5" font-family="JetBrains Mono, monospace" font-weight="800">Rp</text>
              </g>

              <g opacity="0">
                <animateTransform attributeName="transform" type="translate" values="0,0; 78,44" dur="2.6s" begin="1.3s" repeatCount="indefinite" />
                <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;0.15;0.82;1" dur="2.6s" begin="1.3s" repeatCount="indefinite" />
                <ellipse cx="318" cy="244" rx="15" ry="19" fill="url(#coinGrad)" stroke="#EC4899" stroke-width="2" />
                <text x="318" y="248" text-anchor="middle" fill="#1E1B4B" font-size="10.5" font-family="JetBrains Mono, monospace" font-weight="800">Rp</text>
              </g>

              <text x="64" y="90" fill="var(--lp-accent)" font-size="11.5" font-family="JetBrains Mono, monospace" font-weight="600">STOK RESMI OTOMATIS →</text>
              <text x="292" y="324" fill="var(--text)" font-size="11.5" font-family="JetBrains Mono, monospace" font-weight="600">KIRIM INSTAN 24 JAM</text>
            </svg>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================================= -->
    <!-- 2. LAYANAN YANG TERSEDIA (KLIK -> LANGSUNG KE KATALOG)    -->
    <!-- ========================================================= -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-4 space-y-4">
      <div class="flex items-center justify-between gap-2">
        <div>
          <h2 class="text-base sm:text-lg font-bold">Layanan Produk Digital</h2>
          <p class="text-sm mt-0.5 leading-relaxed" style="color: var(--muted)">Pilih kategori untuk melihat daftar harga lengkap di katalog.</p>
        </div>
        <NuxtLink to="/products" class="text-xs font-semibold no-underline shrink-0" style="color: var(--lp-accent)">
          Lihat Katalog →
        </NuxtLink>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <NuxtLink
          v-for="(cat, idx) in activeCategories.slice(0, 8)"
          :key="cat.id"
          :to="`/products?category=${cat.slug}`"
          class="cat-card p-3 sm:p-3.5 rounded-2xl flex items-center gap-3.5 no-underline transition tap-bounce hover:bg-black/5 dark:hover:bg-white/5"
          style="color: var(--text)"
        >
          <span
            class="cat-icon-box w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
            style="background: var(--surface-2); color: var(--lp-accent)"
          >
            <span class="cat-icon-bounce" :style="{ animationDelay: `${idx * 0.28}s` }">
            <svg v-if="categoryIconKey(cat.name) === 'phone'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
            <svg v-else-if="categoryIconKey(cat.name) === 'wifi'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h.01"/><path d="M2 8.82a15 15 0 0 1 20 0"/><path d="M5 12.859a10 10 0 0 1 14 0"/><path d="M8.5 16.429a5 5 0 0 1 7 0"/></svg>
            <svg v-else-if="categoryIconKey(cat.name) === 'game'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="6" x2="10" y1="11" y2="11"/><line x1="8" x2="8" y1="9" y2="13"/><line x1="15" x2="15.01" y1="12" y2="12"/><line x1="18" x2="18.01" y1="10" y2="10"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258-.007-.05-.011-.1-.017-.151A4 4 0 0 0 17.32 5z"/></svg>
            <svg v-else-if="categoryIconKey(cat.name) === 'wallet'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
            <svg v-else-if="categoryIconKey(cat.name) === 'zap'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>
            <svg v-else-if="categoryIconKey(cat.name) === 'ticket'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>
            <svg v-else-if="categoryIconKey(cat.name) === 'tv'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="15" x="2" y="7" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/></svg>
            <svg v-else-if="categoryIconKey(cat.name) === 'call'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <svg v-else-if="categoryIconKey(cat.name) === 'layers'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </span>
          </span>
          <div class="min-w-0">
            <div class="text-xs sm:text-sm font-bold truncate">{{ cat.name }}</div>
            <div class="text-xs font-mono-code mt-0.5" style="color: var(--muted)">{{ cat.products_count }} pilihan</div>
          </div>
        </NuxtLink>
      </div>
    </section>

    <!-- ========================================================= -->
    <!-- 3. KENAPA BELANJA DI UNISELL (PENJELASAN SINGKAT)         -->
    <!-- ========================================================= -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 mt-8 border-t space-y-6" style="border-color: var(--border)">
      <div>
        <h2 class="text-base sm:text-lg font-bold">Kenapa Belanja di UniSell Digital?</h2>
        <p class="text-sm mt-0.5 leading-relaxed" style="color: var(--muted)">Dirancang agar setiap transaksi digital cepat, transparan, dan aman.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <div class="py-2 pr-2 space-y-2.5">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: var(--surface-2); color: var(--lp-accent)">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>
          </div>
          <h3 class="text-sm font-bold">Proses Otomatis 24/7</h3>
          <p class="text-[13px] leading-[1.65]" style="color: var(--muted)">Pesan kapan saja siang atau malam tanpa menunggu balasan admin.</p>
        </div>

        <div class="py-2 pr-2 space-y-2.5">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: var(--surface-2); color: var(--lp-accent)">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
          </div>
          <h3 class="text-sm font-bold">Pembayaran Lengkap</h3>
          <p class="text-[13px] leading-[1.65]" style="color: var(--muted)">Mendukung QRIS, Virtual Account bank besar, hingga e-wallet populer.</p>
        </div>

        <div class="py-2 pr-2 space-y-2.5">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: var(--surface-2); color: var(--lp-accent)">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" x2="22" y1="11" y2="11"/></svg>
          </div>
          <h3 class="text-sm font-bold">Tanpa Daftar Akun</h3>
          <p class="text-[13px] leading-[1.65]" style="color: var(--muted)">Langsung pilih produk, isi nomor tujuan, bayar, dan pantau lewat nomor order.</p>
        </div>

        <div class="py-2 pr-2 space-y-2.5">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: var(--surface-2); color: #22C55E">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
          </div>
          <h3 class="text-sm font-bold">Garansi Refund</h3>
          <p class="text-[13px] leading-[1.65]" style="color: var(--muted)">Setiap pesanan gagal otomatis terpantau dan dilindungi pengembalian dana.</p>
        </div>
      </div>
    </section>

    <!-- ========================================================= -->
    <!-- 4. MARQUEE METODE PEMBAYARAN (LOGO ASLI)                  -->
    <!-- ========================================================= -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 space-y-3">
      <div class="text-center text-sm font-medium" style="color: var(--muted)">
        Mendukung pembayaran otomatis lewat bank &amp; e-wallet favoritmu
      </div>
      <div class="cat-marquee-viewport py-2">
        <div v-for="copy in 2" :key="copy" class="cat-marquee-group" :aria-hidden="copy === 2">
          <div
            v-for="(ch, idx) in [...paymentChannels, ...paymentChannels]"
            :key="`${copy}-${ch.id}-${idx}`"
            class="payment-pill shrink-0 h-14 px-5 rounded-2xl flex items-center justify-center"
            :title="ch.label"
          >
            <img
              :src="ch.logo"
              :alt="ch.label"
              :class="ch.id === 'qris' ? 'h-7 sm:h-8 w-auto max-w-[116px] object-contain logo-invert-dark' : 'h-7 sm:h-8 w-auto max-w-[116px] object-contain logo-boost-dark'"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================================= -->
    <!-- 5. CTA PENUTUP KE KATALOG                                 -->
    <!-- ========================================================= -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-10">
      <div
        class="pt-8 border-t flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
        style="border-color: var(--border)"
      >
        <div class="space-y-1 max-w-xl">
          <h2 class="text-base font-bold">Siap mulai transaksi digitalmu?</h2>
          <p class="text-sm leading-relaxed" style="color: var(--muted)">
            Buka katalog lengkap untuk melihat daftar harga terbaru dan langsung checkout tanpa login.
          </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <NuxtLink
            to="/products"
            class="px-5 py-2.5 rounded-xl text-xs font-semibold no-underline tap-bounce"
            style="background: var(--lp-accent); color: var(--lp-accent-fg)"
          >
            Lihat Katalog →
          </NuxtLink>
          <NuxtLink
            to="/help"
            class="px-4 py-2.5 rounded-xl text-xs font-semibold border no-underline tap-bounce"
            style="background: var(--surface-2); border-color: var(--border); color: var(--text)"
          >
            Bantuan
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
