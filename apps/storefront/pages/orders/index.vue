<script setup lang="ts">
const router = useRouter();
const config = useRuntimeConfig();

const orderNoInput = ref('');
const emailInput = ref('');
const history = ref<Array<{ order_no: string; status: string; total: number; created_at: string }>>([]);

function goToOrder() {
  if (!orderNoInput.value.trim()) return;
  router.push(`/orders/${orderNoInput.value.trim()}`);
}

async function searchByEmail() {
  if (!emailInput.value.trim()) return;
  const res = await $fetch<{ data: typeof history.value }>(
    `${config.public.apiBase}/orders/history`,
    { query: { email: emailInput.value.trim() } }
  );
  history.value = res.data;
}

function rp(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-6 py-10 space-y-6">
    <h1 class="text-2xl font-bold">Lacak &amp; Riwayat Pesanan</h1>

    <form class="unisell-card p-5 space-y-3" @submit.prevent="goToOrder">
      <div class="text-sm font-semibold">Cek dengan Nomor Pesanan</div>
      <div class="flex gap-2">
        <input v-model="orderNoInput" class="unisell-input font-mono-code text-sm" placeholder="US-20261123-XXXXXX" />
        <button type="submit" class="unisell-btn-primary text-sm shrink-0">Lacak</button>
      </div>
    </form>

    <form class="unisell-card p-5 space-y-3" @submit.prevent="searchByEmail">
      <div class="text-sm font-semibold">Cari Riwayat dengan Email</div>
      <div class="flex gap-2">
        <input v-model="emailInput" type="email" class="unisell-input text-sm" placeholder="email@contoh.com" />
        <button type="submit" class="unisell-btn-secondary text-sm shrink-0">Cari</button>
      </div>
    </form>

    <div v-if="history.length" class="unisell-card p-5 space-y-3">
      <div class="text-sm font-semibold">Hasil Riwayat</div>
      <NuxtLink
        v-for="o in history"
        :key="o.order_no"
        :to="`/orders/${o.order_no}`"
        class="unisell-card-elevated p-4 flex items-center justify-between no-underline"
      >
        <div>
          <div class="font-mono-code font-semibold" style="color: var(--primary)">{{ o.order_no }}</div>
          <div class="text-xs" style="color: var(--muted)">{{ rp(o.total) }}</div>
        </div>
        <span class="unisell-badge font-mono-code">{{ o.status }}</span>
      </NuxtLink>
    </div>
  </div>
</template>
