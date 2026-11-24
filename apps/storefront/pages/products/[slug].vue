<script setup lang="ts">
const route = useRoute();
const router = useRouter();
const config = useRuntimeConfig();

interface ProductDetail {
  id: number;
  name: string;
  slug: string;
  description?: string;
  sell_price: number;
  category?: { name: string; slug: string };
}

const { data: productResp, error } = await useFetch<{ data: ProductDetail }>(
  `${config.public.apiBase}/products/${route.params.slug}`
);

const qty = ref(1);
const guestName = ref('');
const guestEmail = ref('');
const targetAccount = ref('');
const paymentMethod = ref<'qris' | 'va' | 'ewallet'>('qris');
const submitting = ref(false);
const errorMsg = ref('');

const totalPrice = computed(() => (productResp.value?.data?.sell_price || 0) * qty.value);

function rp(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
}

async function handleCheckout() {
  if (!productResp.value?.data) return;
  errorMsg.value = '';
  submitting.value = true;
  try {
    const res = await $fetch<{ data: { order: { order_no: string } } }>(
      `${config.public.apiBase}/orders`,
      {
        method: 'POST',
        body: {
          product_id: productResp.value.data.id,
          qty: qty.value,
          channel: 'website',
          guest_name: guestName.value || 'Pembeli Guest',
          guest_email: guestEmail.value,
          payment_method: paymentMethod.value,
          customer_input: {
            target_account: targetAccount.value,
          },
        },
      }
    );

    await router.push(`/orders/${res.data.order.order_no}`);
  } catch (e: any) {
    errorMsg.value = e?.data?.error?.message || 'Gagal membuat pesanan.';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="max-w-4xl mx-auto px-6 py-10 space-y-6">
    <NuxtLink to="/products" class="text-sm" style="color: var(--muted)">← Kembali ke Katalog</NuxtLink>

    <div v-if="error || !productResp?.data" class="unisell-card p-8 text-center">
      Produk tidak ditemukan atau sedang tidak tersedia.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="unisell-card p-6 space-y-4">
        <span v-if="productResp.data.category" class="unisell-badge text-xs" style="color: var(--accent)">
          {{ productResp.data.category.name }}
        </span>
        <h1 class="text-2xl font-bold">{{ productResp.data.name }}</h1>
        <p class="text-sm" style="color: var(--muted)">{{ productResp.data.description }}</p>
        <div class="text-3xl font-bold font-mono-code pt-2" style="color: var(--primary)">
          {{ rp(productResp.data.sell_price) }}
        </div>
        <div class="text-xs" style="color: var(--success)">
          ● Proses otomatis langsung setelah pembayaran terverifikasi
        </div>
      </div>

      <form class="unisell-card p-6 space-y-4" @submit.prevent="handleCheckout">
        <h2 class="text-lg font-semibold">Checkout Instan</h2>

        <div v-if="errorMsg" class="p-3 rounded-lg text-xs border" style="border-color: var(--danger); color: var(--danger)">
          {{ errorMsg }}
        </div>

        <label class="block space-y-1 text-sm">
          <span>Nama</span>
          <input v-model="guestName" required class="unisell-input" placeholder="Nama Anda" />
        </label>

        <label class="block space-y-1 text-sm">
          <span>Email (untuk riwayat &amp; bukti pesanan)</span>
          <input v-model="guestEmail" type="email" required class="unisell-input" placeholder="email@contoh.com" />
        </label>

        <label class="block space-y-1 text-sm">
          <span>ID Game / Akun Tujuan (opsional)</span>
          <input v-model="targetAccount" class="unisell-input font-mono-code" placeholder="Misal: 12345678 (2044)" />
        </label>

        <div class="grid grid-cols-2 gap-3">
          <label class="block space-y-1 text-sm">
            <span>Jumlah</span>
            <input v-model.number="qty" type="number" min="1" max="50" class="unisell-input font-mono-code" />
          </label>
          <label class="block space-y-1 text-sm">
            <span>Metode Bayar</span>
            <select v-model="paymentMethod" class="unisell-input">
              <option value="qris">QRIS</option>
              <option value="va">Virtual Account</option>
              <option value="ewallet">E-Wallet</option>
            </select>
          </label>
        </div>

        <div class="flex items-center justify-between pt-3 border-t text-sm" style="border-color: var(--border)">
          <span>Total Pembayaran</span>
          <strong class="text-lg font-mono-code" style="color: var(--primary)">{{ rp(totalPrice) }}</strong>
        </div>

        <button type="submit" class="unisell-btn-primary w-full" :disabled="submitting">
          {{ submitting ? 'Membuat Pesanan...' : 'Buat Pesanan & Bayar' }}
        </button>
      </form>
    </div>
  </div>
</template>
