<script setup lang="ts">
const route = useRoute();
const config = useRuntimeConfig();

const searchQuery = ref((route.query.q as string) || '');
const selectedCategory = ref((route.query.category as string) || '');

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
}

const { data: categoriesResp } = await useFetch<{ data: CategoryItem[] }>(
  `${config.public.apiBase}/products/categories`
);

const { data: productsResp, refresh } = await useFetch<{ data: ProductItem[] }>(
  `${config.public.apiBase}/products`,
  {
    query: computed(() => ({
      q: searchQuery.value || undefined,
      category: selectedCategory.value || undefined,
    })),
  }
);

function rp(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
}
</script>

<template>
  <div class="max-w-6xl mx-auto px-6 py-10 space-y-6">
    <div class="space-y-2">
      <h1 class="text-2xl font-bold">Katalog Produk Digital</h1>
      <p class="text-sm" style="color: var(--muted)">
        Pilih produk digital yang tersedia dan selesaikan checkout dalam hitungan detik.
      </p>
    </div>

    <div class="unisell-card p-4 flex flex-wrap gap-3">
      <input
        v-model="searchQuery"
        type="search"
        class="unisell-input flex-1 min-w-[200px] text-sm"
        placeholder="Cari game, voucher, lisensi..."
        @input="refresh()"
      />
      <select
        v-model="selectedCategory"
        class="unisell-input w-auto text-sm"
        @change="refresh()"
      >
        <option value="">Semua Kategori</option>
        <option v-for="cat in categoriesResp?.data || []" :key="cat.id" :value="cat.slug">
          {{ cat.name }}
        </option>
      </select>
    </div>

    <div v-if="!(productsResp?.data || []).length" class="unisell-card p-8 text-center text-sm" style="color: var(--muted)">
      Tidak ada produk yang cocok dengan pencarian Anda.
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <NuxtLink
        v-for="product in productsResp?.data || []"
        :key="product.id"
        :to="`/products/${product.slug}`"
        class="unisell-card p-5 flex flex-col justify-between gap-4 no-underline"
      >
        <div class="space-y-2">
          <span v-if="product.category" class="unisell-badge text-xs" style="color: var(--accent)">
            {{ product.category.name }}
          </span>
          <h2 class="text-base font-semibold" style="color: var(--text)">{{ product.name }}</h2>
          <p class="text-xs" style="color: var(--muted)">{{ product.description }}</p>
        </div>
        <div class="flex items-center justify-between pt-3 border-t" style="border-color: var(--border)">
          <span class="text-lg font-bold font-mono-code" style="color: var(--primary)">
            {{ rp(product.sell_price) }}
          </span>
          <span class="text-xs font-semibold" style="color: var(--success)">Checkout →</span>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
