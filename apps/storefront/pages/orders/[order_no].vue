<script setup lang="ts">
const route = useRoute();
const config = useRuntimeConfig();
const orderNo = route.params.order_no as string;

interface OrderDetail {
  id: number;
  order_no: string;
  status: string;
  total: number;
  guest_name?: string;
  guest_email?: string;
  items: Array<{ qty: number; unit_price: number; product?: { name: string } }>;
  payment?: { ref: string; gateway: string; status: string; amount: number; fee: number };
  deliveries: Array<{ id: number; type: string; payload: string; delivered_at: string }>;
  created_at: string;
}

const { data: orderResp, refresh } = await useFetch<{ data: OrderDetail }>(
  `${config.public.apiBase}/orders/${orderNo}`
);

const paying = ref(false);
const notice = ref('');

async function simulatePayment() {
  const refCode = orderResp.value?.data?.payment?.ref;
  if (!refCode) return;
  paying.value = true;
  notice.value = '';
  try {
    await $fetch(`${config.public.apiBase}/payments/simulate-paid`, {
      method: 'POST',
      body: { payment_ref: refCode },
    });
    notice.value = 'Pembayaran terverifikasi! Memproses pesanan ke supplier...';
    setTimeout(() => refresh(), 1200);
    setTimeout(() => refresh(), 3000);
  } catch (e: any) {
    notice.value = e?.data?.error?.message || 'Gagal mensimulasikan pembayaran.';
  } finally {
    paying.value = false;
  }
}

function rp(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-6 py-10 space-y-6">
    <div v-if="!orderResp?.data" class="unisell-card p-8 text-center">
      Pesanan <span class="font-mono-code">{{ orderNo }}</span> tidak ditemukan.
    </div>

    <template v-else>
      <div class="unisell-card p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="text-xs uppercase" style="color: var(--muted)">Nomor Pesanan</div>
            <h1 class="text-2xl font-bold font-mono-code" style="color: var(--primary)">
              {{ orderResp.data.order_no }}
            </h1>
          </div>
          <span
            class="unisell-badge font-mono-code text-sm"
            :style="{
              color:
                orderResp.data.status === 'delivered'
                  ? 'var(--success)'
                  : orderResp.data.status === 'manual_review'
                    ? 'var(--danger)'
                    : 'var(--warning)',
            }"
          >
            ● {{ orderResp.data.status }}
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm pt-2 border-t" style="border-color: var(--border)">
          <div>
            <span style="color: var(--muted)">Produk:</span>
            <div class="font-medium">
              {{ orderResp.data.items[0]?.product?.name }} × {{ orderResp.data.items[0]?.qty }}
            </div>
          </div>
          <div>
            <span style="color: var(--muted)">Total:</span>
            <div class="font-bold font-mono-code">{{ rp(orderResp.data.total) }}</div>
          </div>
        </div>

        <div v-if="notice" class="p-3 rounded-lg text-xs" style="background: var(--surface-2); color: var(--primary)">
          {{ notice }}
        </div>

        <div
          v-if="orderResp.data.status === 'pending_payment' && orderResp.data.payment"
          class="unisell-card-elevated p-5 space-y-3"
        >
          <div class="text-sm font-semibold">Instruksi Pembayaran</div>
          <div class="text-xs font-mono-code" style="color: var(--muted)">
            Ref: {{ orderResp.data.payment.ref }} · Gateway: {{ orderResp.data.payment.gateway }}
          </div>
          <button type="button" class="unisell-btn-primary text-sm" :disabled="paying" @click="simulatePayment">
            {{ paying ? 'Memverifikasi Webhook...' : 'Simulasikan Pembayaran Sukses (Sandbox Lokal)' }}
          </button>
        </div>

        <div v-if="orderResp.data.deliveries?.length" class="unisell-card-elevated p-5 space-y-2" style="border-color: var(--success)">
          <div class="text-sm font-semibold" style="color: var(--success)">
            ✓ Produk Digital Terkirim
          </div>
          <pre
            v-for="d in orderResp.data.deliveries"
            :key="d.id"
            class="p-3 rounded text-xs font-mono-code whitespace-pre-wrap"
            style="background: var(--bg); color: var(--text)"
          >{{ d.payload }}</pre>
        </div>

        <div class="flex gap-2">
          <button type="button" class="unisell-btn-secondary text-xs" @click="refresh()">
            Refresh Status
          </button>
          <NuxtLink to="/products" class="unisell-btn-secondary text-xs no-underline">
            Belanja Lagi
          </NuxtLink>
        </div>
      </div>
    </template>
  </div>
</template>
