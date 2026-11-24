<script setup lang="ts">
import { computed, ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import AdminLayout from '../components/AdminLayout.vue';
import { useAuthStore } from '../stores/auth';
import { apiRequest } from '../lib/api';

const auth = useAuthStore();
const message = ref('');

interface StorefrontProduct {
  id: number;
  name: string;
  slug: string;
  status: string;
  price?: { mode: string; markup_value: number; sell_price: number };
  mappings: Array<{ supplier_product?: { name: string; cost_price: number; supplier?: { name: string } } }>;
}

interface SupplierProductItem {
  id: number;
  external_id: string;
  name: string;
  cost_price: number;
  stock_status: string;
  supplier?: { id: number; name: string; status: string };
}

const { data: products, refetch } = useQuery({
  queryKey: ['admin-products'],
  queryFn: () => apiRequest<{ data: StorefrontProduct[] }>('/admin/products', {}, auth.token),
});

const { data: supplierProducts, refetch: refetchSp } = useQuery({
  queryKey: ['admin-supplier-products'],
  queryFn: () => apiRequest<{ data: SupplierProductItem[] }>('/admin/supplier-products', {}, auth.token),
});

// Add Supplier Product -> Storefront Product Form
const selectedSpId = ref<number | ''>('');
const newProductForm = ref({
  name: '',
  description: '',
  pricing_mode: 'fixed',
  markup_value: 4000,
  status: 'active',
});

function pickSupplierProduct(sp: SupplierProductItem) {
  selectedSpId.value = sp.id;
  newProductForm.value.name = sp.name;
  newProductForm.value.description = `Produk digital resmi dari ${sp.supplier?.name || 'supplier'}.`;
}

async function createStorefrontProduct() {
  if (!selectedSpId.value) return;
  message.value = '';
  try {
    await apiRequest(
      '/admin/products',
      {
        method: 'POST',
        body: JSON.stringify({
          supplier_product_id: selectedSpId.value,
          name: newProductForm.value.name,
          description: newProductForm.value.description,
          pricing_mode: newProductForm.value.pricing_mode,
          markup_value: newProductForm.value.markup_value,
          status: newProductForm.value.status,
        }),
      },
      auth.token
    );
    message.value = 'Produk berhasil ditambahkan ke katalog toko dan langsung tampil di Storefront!';
    selectedSpId.value = '';
    newProductForm.value.name = '';
    refetch();
    refetchSp();
  } catch (e: any) {
    message.value = e?.message || 'Gagal menambahkan produk (pastikan supplier memiliki langganan aktif).';
  }
}

// Scenario Modeler
const activeScenario = ref<'konservatif' | 'realistis' | 'optimis'>('realistis');
const costPrice = ref(19500);
const markupValue = ref(4500);
const gatewayFee = ref(500);
const subscriptionCost = ref(150000);
const monthlyTxEstimate = ref(80);

function applyScenario(scenario: 'konservatif' | 'realistis' | 'optimis') {
  activeScenario.value = scenario;
  if (scenario === 'konservatif') {
    markupValue.value = 3000;
    monthlyTxEstimate.value = 40;
  } else if (scenario === 'realistis') {
    markupValue.value = 4500;
    monthlyTxEstimate.value = 80;
  } else {
    markupValue.value = 6500;
    monthlyTxEstimate.value = 160;
  }
}

const sellPrice = computed(() => costPrice.value + markupValue.value);
const netProfitPerTx = computed(() => sellPrice.value - costPrice.value - gatewayFee.value);
const breakEvenTx = computed(() =>
  netProfitPerTx.value > 0 ? Math.ceil(subscriptionCost.value / netProfitPerTx.value) : null
);
const projectedMonthlyNet = computed(
  () => netProfitPerTx.value * monthlyTxEstimate.value - subscriptionCost.value
);
const breakEvenProgress = computed(() => {
  if (!breakEvenTx.value) return 0;
  return Math.min(100, Math.round((monthlyTxEstimate.value / breakEvenTx.value) * 100));
});

async function publishProduct(id: number) {
  message.value = '';
  try {
    await apiRequest(`/admin/products/${id}/publish`, { method: 'POST' }, auth.token);
    message.value = 'Produk berhasil dipublikasikan ke storefront.';
    refetch();
  } catch (e: any) {
    message.value = e?.message || 'Gagal mempublikasikan produk.';
  }
}

function rp(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
}
</script>

<template>
  <AdminLayout
    section="Workspace"
    title="Products & Pricing"
    subtitle="Pilih produk dari supplier_products untuk dipublikasikan ke katalog toko & hitung titik balik modal"
  >
    <div v-if="message" class="p-3.5 rounded-xl border text-xs font-medium" style="background: var(--surface-2); border-color: var(--border); color: var(--text)">
      {{ message }}
    </div>

    <!-- Step 4: Publish Supplier Product to Storefront -->
    <section class="unisell-card p-5 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 class="text-sm font-bold">Pilih Produk Supplier → Publikasikan ke Toko</h2>
          <p class="text-[11px]" style="color: var(--muted)">
            Produk hasil Sync API atau Upload Manual di menu Suppliers muncul di sini untuk dipilih satu per satu
          </p>
        </div>
        <RouterLink to="/suppliers" class="unisell-btn-secondary text-xs no-underline">
          + Sync / Upload di Suppliers
        </RouterLink>
      </div>

      <form class="grid grid-cols-1 md:grid-cols-6 gap-3" @submit.prevent="createStorefrontProduct">
        <select
          v-model="selectedSpId"
          required
          class="unisell-input text-xs md:col-span-2"
          @change="() => { const found = (supplierProducts?.data || []).find(i => i.id === selectedSpId); if (found) pickSupplierProduct(found); }"
        >
          <option value="">-- Pilih Produk Supplier --</option>
          <option v-for="sp in supplierProducts?.data || []" :key="sp.id" :value="sp.id">
            [{{ sp.supplier?.name }}] {{ sp.name }} (Modal {{ rp(sp.cost_price) }})
          </option>
        </select>

        <input v-model="newProductForm.name" required class="unisell-input text-xs" placeholder="Nama di Storefront" />

        <select v-model="newProductForm.pricing_mode" class="unisell-input text-xs">
          <option value="fixed">Fixed (+Rp)</option>
          <option value="percent">Percent (+%)</option>
          <option value="manual">Manual</option>
        </select>

        <input v-model.number="newProductForm.markup_value" type="number" min="0" class="unisell-input text-xs" placeholder="Markup" />

        <button type="submit" class="unisell-btn-primary text-xs">
          Publikasikan ke Toko
        </button>
      </form>
    </section>

    <!-- Interactive Scenario Modeler -->
    <section class="unisell-card p-5 space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-bold">Interactive Break-Even &amp; Margin Model</h2>
          <p class="text-[11px]" style="color: var(--muted)">
            Geser slider untuk menghitung proyeksi laba bersih &amp; jumlah transaksi balik modal langganan
          </p>
        </div>

        <div class="flex gap-1.5 p-1 rounded-xl border" style="background: var(--surface-2); border-color: var(--border)">
          <button
            v-for="sc in (['konservatif', 'realistis', 'optimis'] as const)"
            :key="sc"
            type="button"
            class="px-3 py-1 rounded-lg text-xs font-medium capitalize transition cursor-pointer"
            :style="
              activeScenario === sc
                ? { background: 'var(--primary)', color: 'var(--primary-fg)' }
                : { color: 'var(--muted)' }
            "
            @click="applyScenario(sc)"
          >
            {{ sc }}
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="unisell-card-elevated p-4 space-y-2">
          <div class="flex justify-between text-xs">
            <span style="color: var(--muted)">Markup / Produk</span>
            <strong class="font-mono-code">+{{ rp(markupValue) }}</strong>
          </div>
          <input v-model.number="markupValue" type="range" min="1000" max="20000" step="500" class="unisell-slider" />
        </div>

        <div class="unisell-card-elevated p-4 space-y-2">
          <div class="flex justify-between text-xs">
            <span style="color: var(--muted)">Biaya Langganan</span>
            <strong class="font-mono-code">{{ rp(subscriptionCost) }}</strong>
          </div>
          <input v-model.number="subscriptionCost" type="range" min="0" max="1000000" step="25000" class="unisell-slider" />
        </div>

        <div class="unisell-card-elevated p-4 space-y-2">
          <div class="flex justify-between text-xs">
            <span style="color: var(--muted)">Target Tx / Bulan</span>
            <strong class="font-mono-code">{{ monthlyTxEstimate }} tx</strong>
          </div>
          <input v-model.number="monthlyTxEstimate" type="range" min="5" max="500" step="5" class="unisell-slider" />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t" style="border-color: var(--border)">
        <div>
          <div class="text-[11px] uppercase" style="color: var(--muted)">Balik Modal (BEP)</div>
          <div class="text-xl font-bold font-mono-code">
            {{ breakEvenTx ? `${breakEvenTx} transaksi` : 'Tidak Profit' }}
          </div>
        </div>

        <div>
          <div class="text-[11px] uppercase" style="color: var(--muted)">Proyeksi Laba Bersih / Bulan</div>
          <div
            class="text-xl font-bold font-mono-code"
            :style="{ color: projectedMonthlyNet >= 0 ? 'var(--success)' : 'var(--danger)' }"
          >
            {{ rp(projectedMonthlyNet) }}
          </div>
        </div>

        <div class="space-y-1.5">
          <div class="flex justify-between text-[11px]">
            <span style="color: var(--muted)">Progress menuju BEP</span>
            <span class="font-mono-code font-semibold">{{ breakEvenProgress }}%</span>
          </div>
          <div class="h-2 rounded-full overflow-hidden" style="background: var(--surface-2)">
            <div
              class="h-full rounded-full transition-all"
              :style="{
                width: `${breakEvenProgress}%`,
                background: breakEvenProgress >= 100 ? 'var(--success)' : 'var(--primary)',
              }"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- Katalog Produk Toko -->
    <section class="unisell-card overflow-hidden">
      <div class="px-5 py-3.5 border-b flex items-center justify-between" style="border-color: var(--border)">
        <h2 class="text-sm font-bold">Katalog Produk Toko</h2>
        <span class="text-xs font-mono-code" style="color: var(--muted)">
          {{ (products?.data || []).length }} produk
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="unisell-table">
          <thead>
            <tr>
              <th>Produk</th>
              <th>Supplier</th>
              <th>Modal</th>
              <th>Harga Jual</th>
              <th>Margin</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in products?.data || []" :key="p.id">
              <td>
                <div class="font-medium">{{ p.name }}</div>
                <div class="text-[11px] font-mono-code" style="color: var(--muted)">/{{ p.slug }}</div>
              </td>
              <td class="text-xs">{{ p.mappings[0]?.supplier_product?.supplier?.name || '-' }}</td>
              <td class="font-mono-code">{{ rp(p.mappings[0]?.supplier_product?.cost_price || 0) }}</td>
              <td class="font-mono-code font-semibold">{{ rp(p.price?.sell_price || 0) }}</td>
              <td class="font-mono-code" style="color: var(--success)">
                +{{ rp((p.price?.sell_price || 0) - (p.mappings[0]?.supplier_product?.cost_price || 0)) }}
              </td>
              <td>
                <span
                  class="unisell-badge font-mono-code"
                  :style="{
                    color:
                      p.status === 'active'
                        ? 'var(--success)'
                        : p.status === 'locked'
                          ? 'var(--danger)'
                          : 'var(--warning)',
                  }"
                >
                  ● {{ p.status }}
                </span>
              </td>
              <td>
                <button
                  v-if="p.status !== 'active'"
                  type="button"
                  class="unisell-btn-secondary text-xs py-1 px-2.5"
                  @click="publishProduct(p.id)"
                >
                  Publikasikan
                </button>
                <span v-else class="text-xs" style="color: var(--muted)">Live</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AdminLayout>
</template>
