<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import AdminLayout from '../components/AdminLayout.vue';
import { useAuthStore } from '../stores/auth';
import { useThemeStore } from '../stores/theme';
import { apiRequest } from '../lib/api';

const auth = useAuthStore();
const theme = useThemeStore();
const search = ref('');
const filterTab = ref<'all' | 'delivered' | 'review'>('all');
const selectedId = ref<number | null>(null);
const message = ref('');

interface OrderRow {
  id: number;
  order_no: string;
  channel: string;
  status: string;
  total: number;
  guest_name?: string;
  guest_email?: string;
  items: Array<{ qty: number; unit_price: number; product?: { name: string } }>;
  latest_payment?: { ref: string; gateway: string; status: string; amount: number; fee: number };
  created_at: string;
}

const { data, refetch } = useQuery({
  queryKey: ['admin-orders'],
  queryFn: () => apiRequest<{ data: OrderRow[] }>('/admin/orders', {}, auth.token),
});

const filteredOrders = computed(() => {
  const list = data.value?.data || [];
  return list.filter((o) => {
    if (filterTab.value === 'delivered' && o.status !== 'delivered') return false;
    if (filterTab.value === 'review' && !['manual_review', 'failed_retry', 'pending_payment'].includes(o.status)) {
      return false;
    }
    if (search.value.trim()) {
      const q = search.value.toLowerCase();
      return (
        o.order_no.toLowerCase().includes(q) ||
        (o.guest_name || '').toLowerCase().includes(q) ||
        (o.guest_email || '').toLowerCase().includes(q) ||
        (o.items[0]?.product?.name || '').toLowerCase().includes(q)
      );
    }
    return true;
  });
});

watch(
  filteredOrders,
  (list) => {
    if (!selectedId.value && list.length) {
      selectedId.value = list[0].id;
    }
  },
  { immediate: true }
);

const selectedOrder = computed(
  () => filteredOrders.value.find((o) => o.id === selectedId.value) || filteredOrders.value[0] || null
);

async function retryOrder(id: number) {
  await apiRequest(`/admin/orders/${id}/retry`, { method: 'POST' }, auth.token);
  message.value = 'Supplier order dijadwalkan ulang ke Redis queue.';
  refetch();
}

async function manualDeliver(id: number) {
  const code = window.prompt('Masukkan kode voucher / lisensi untuk dikirim:');
  if (!code) return;
  await apiRequest(
    `/admin/orders/${id}/manual-deliver`,
    { method: 'POST', body: JSON.stringify({ type: 'code', payload: code }) },
    auth.token
  );
  message.value = 'Delivery manual berhasil dikirim.';
  refetch();
}

async function refundOrder(id: number) {
  const reason = window.prompt('Alasan refund:', 'Supplier gagal memproses pesanan');
  if (!reason) return;
  await apiRequest(
    `/admin/orders/${id}/refund`,
    { method: 'POST', body: JSON.stringify({ reason }) },
    auth.token
  );
  message.value = 'Order berhasil di-refund.';
  refetch();
}

function rp(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
}

function formatTime(iso: string) {
  try {
    return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  } catch {
    return '';
  }
}
</script>

<template>
  <AdminLayout title="Orders Inbox" :flush-main="true">
    <!-- Middle Pane: Dappr Inbox List (Exact Dribbble 22461595 Layout) -->
    <section
      class="w-full lg:w-[360px] shrink-0 border-b lg:border-b-0 lg:border-r flex flex-col lg:h-screen lg:sticky lg:top-0"
      style="background: var(--surface-2); border-color: var(--border)"
    >
      <div class="p-6 pb-4 space-y-4">
        <div class="flex items-center justify-between">
          <h1 class="text-xl font-bold tracking-tight">Orders</h1>
          <button
            type="button"
            class="w-8 h-8 rounded-full flex items-center justify-center text-base cursor-pointer"
            style="background: var(--primary); color: var(--primary-fg)"
            title="Refresh Orders"
            @click="refetch()"
          >
            ↻
          </button>
        </div>

        <!-- Rounded White Pill Search -->
        <div class="relative">
          <input
            v-model="search"
            type="search"
            class="w-full pl-9 pr-4 py-2.5 rounded-full text-xs border-0 outline-none shadow-sm"
            style="background: var(--surface); color: var(--text)"
            placeholder="Search orders..."
          />
          <span class="absolute left-3.5 top-2.5 text-xs" style="color: var(--muted)">🔍</span>
        </div>

        <!-- Segmented Pill Filter (All in solid black pill) -->
        <div class="p-1 rounded-2xl flex items-center gap-1 shadow-sm" style="background: var(--surface)">
          <button
            v-for="tab in ([
              { id: 'all', label: 'All' },
              { id: 'delivered', label: 'Delivered' },
              { id: 'review', label: 'Pending' },
            ] as const)"
            :key="tab.id"
            type="button"
            class="flex-1 py-2 rounded-xl text-xs font-medium transition cursor-pointer"
            :style="
              filterTab === tab.id
                ? { background: 'var(--primary)', color: 'var(--primary-fg)' }
                : { color: 'var(--muted)', background: 'transparent' }
            "
            @click="filterTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <!-- Scrollable Order List -->
      <div class="flex-1 overflow-y-auto px-3 pb-6 space-y-1">
        <div v-if="!filteredOrders.length" class="p-8 text-center text-xs" style="color: var(--muted)">
          Tidak ada pesanan pada filter ini.
        </div>

        <button
          v-for="o in filteredOrders"
          :key="o.id"
          type="button"
          class="w-full text-left p-4 rounded-2xl transition cursor-pointer border-b"
          :style="
            selectedOrder?.id === o.id
              ? { background: 'var(--surface)', borderColor: 'transparent', boxShadow: '0 4px 16px rgba(30,27,75,0.06)' }
              : { background: 'transparent', borderColor: 'var(--border)' }
          "
          @click="selectedId = o.id"
        >
          <div class="flex items-start gap-2.5">
            <span
              class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0"
              :style="{
                background:
                  o.status === 'delivered'
                    ? '#22C55E'
                    : o.status === 'manual_review'
                      ? '#EF4444'
                      : '#F59E0B',
              }"
            />
            <div class="flex-1 min-w-0 space-y-1">
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-bold truncate" style="color: var(--text)">
                  {{ o.guest_name || o.order_no }}
                </span>
                <span class="text-[11px] font-mono-code shrink-0" style="color: var(--primary)">
                  {{ formatTime(o.created_at) }}
                </span>
              </div>
              <div class="text-xs font-medium truncate" style="color: var(--text)">
                {{ o.items[0]?.product?.name || 'Digital Product' }} · {{ rp(o.total) }}
              </div>
              <div class="text-[11px] font-mono-code truncate" style="color: var(--muted)">
                {{ o.order_no }} · {{ o.status }}
              </div>
            </div>
          </div>
        </button>
      </div>
    </section>

    <!-- Right Pane: Pure White Detail View with Dappr Top Action Bar -->
    <section class="flex-1 min-w-0 flex flex-col" style="background: var(--surface)">
      <div v-if="!selectedOrder" class="flex-1 flex items-center justify-center text-sm" style="color: var(--muted)">
        Pilih pesanan di sebelah kiri untuk melihat detail.
      </div>

      <template v-else>
        <!-- Crisp Top Action Toolbar (matching Dappr Reply / Forward / Delete bar) -->
        <header class="px-8 py-5 border-b flex flex-wrap items-center justify-between gap-4" style="border-color: var(--border)">
          <div class="flex flex-wrap items-center gap-5 text-xs font-medium">
            <button
              type="button"
              class="flex items-center gap-1.5 cursor-pointer hover:opacity-75"
              style="color: var(--text)"
              @click="retryOrder(selectedOrder.id)"
            >
              <span>↻</span>
              <span>Retry Supplier</span>
            </button>
            <button
              type="button"
              class="flex items-center gap-1.5 cursor-pointer hover:opacity-75"
              style="color: var(--text)"
              @click="manualDeliver(selectedOrder.id)"
            >
              <span>✓</span>
              <span>Manual Deliver</span>
            </button>
            <button
              type="button"
              class="flex items-center gap-1.5 cursor-pointer hover:opacity-75"
              style="color: var(--danger)"
              @click="refundOrder(selectedOrder.id)"
            >
              <span>↩</span>
              <span>Refund</span>
            </button>
          </div>

          <span
            class="unisell-badge font-mono-code"
            :style="{
              color:
                selectedOrder.status === 'delivered'
                  ? '#16A34A'
                  : selectedOrder.status === 'manual_review'
                    ? '#DC2626'
                    : 'var(--primary)',
            }"
          >
            ● {{ selectedOrder.status }}
          </span>
        </header>

        <div class="p-8 space-y-6 max-w-3xl">
          <div v-if="message" class="p-3 rounded-xl text-xs" style="background: var(--surface-2); color: var(--primary)">
            {{ message }}
          </div>

          <!-- Sender / Buyer Header -->
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3.5">
              <div
                class="w-11 h-11 rounded-full flex items-center justify-center text-sm font-bold"
                style="background: var(--surface-2); color: var(--primary)"
              >
                {{ (selectedOrder.guest_name || 'G').charAt(0).toUpperCase() }}
              </div>
              <div>
                <div class="text-base font-bold">{{ selectedOrder.guest_name || 'Guest Buyer' }}</div>
                <div class="text-xs font-medium mt-0.5">{{ selectedOrder.items[0]?.product?.name }}</div>
                <div class="text-xs mt-1" style="color: var(--muted)">
                  Email: <span style="color: var(--text)">{{ selectedOrder.guest_email || '-' }}</span> ·
                  Channel: <span class="uppercase" style="color: var(--text)">{{ selectedOrder.channel }}</span>
                </div>
              </div>
            </div>

            <div class="text-right">
              <div class="text-xs font-mono-code" style="color: var(--muted)">{{ selectedOrder.order_no }}</div>
              <div class="text-xl font-bold font-mono-code mt-1" style="color: var(--primary)">
                {{ rp(selectedOrder.total) }}
              </div>
            </div>
          </div>

          <!-- Order & Payment Breakdown -->
          <div class="p-6 rounded-2xl space-y-4" style="background: var(--surface-2)">
            <div class="text-xs font-bold uppercase tracking-wider" style="color: var(--muted)">
              Detail Transaksi
            </div>
            <div class="grid grid-cols-2 gap-4 text-xs">
              <div>
                <div style="color: var(--muted)">Produk</div>
                <div class="font-semibold mt-0.5">
                  {{ selectedOrder.items[0]?.product?.name }} × {{ selectedOrder.items[0]?.qty || 1 }}
                </div>
              </div>
              <div>
                <div style="color: var(--muted)">Total Pembayaran</div>
                <div class="font-mono-code font-semibold mt-0.5">{{ rp(selectedOrder.total) }}</div>
              </div>
              <div>
                <div style="color: var(--muted)">Payment Ref</div>
                <div class="font-mono-code mt-0.5">{{ selectedOrder.latest_payment?.ref || '-' }}</div>
              </div>
              <div>
                <div style="color: var(--muted)">Gateway Fee</div>
                <div class="font-mono-code mt-0.5">{{ rp(selectedOrder.latest_payment?.fee || 0) }}</div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </section>
  </AdminLayout>
</template>
