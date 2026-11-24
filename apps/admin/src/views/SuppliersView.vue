<script setup lang="ts">
import { ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import AdminLayout from '../components/AdminLayout.vue';
import { useAuthStore } from '../stores/auth';
import { apiRequest } from '../lib/api';

const auth = useAuthStore();
const message = ref('');

const form = ref({
  name: '',
  website: '',
  auth_method: 'api_key',
  api_key: '',
  api_secret: '',
});

const activePanel = ref<{ supplierId: number; mode: 'package' | 'manual' | 'credentials' } | null>(null);
const pkgForm = ref({ name: 'Paket Pro 30 Hari', price: 150000, period_days: 30, product_limit: 100 });
const manualForm = ref({ external_id: '', name: '', cost_price: 25000 });
const credForm = ref({ api_id: '', username: '', api_key: '', api_secret: '', base_url: '', sandbox: true });

interface SupplierItem {
  id: number;
  name: string;
  auth_method: string;
  status: string;
  health_status: string;
  balance: number;
  has_credentials: boolean;
  has_active_subscription: boolean;
  active_subscription?: { id: number; ends_at: string; package?: { name: string; price: number } } | null;
  packages: Array<{ id: number; name: string; price: number; period_days: number }>;
  supplier_products_count: number;
}

const { data, refetch } = useQuery({
  queryKey: ['admin-suppliers'],
  queryFn: () => apiRequest<{ data: SupplierItem[] }>('/admin/suppliers', {}, auth.token),
});

async function createSupplier() {
  message.value = '';
  const payload: Record<string, unknown> = {
    name: form.value.name,
    website: form.value.website || null,
    auth_method: form.value.auth_method,
  };
  if (form.value.api_key || form.value.api_secret) {
    payload.credentials = {
      api_key: form.value.api_key,
      api_secret: form.value.api_secret,
    };
  }
  await apiRequest('/admin/suppliers', { method: 'POST', body: JSON.stringify(payload) }, auth.token);
  form.value = { name: '', website: '', auth_method: 'api_key', api_key: '', api_secret: '' };
  message.value = '1) Supplier ditambahkan (kredensial terenkripsi). Lanjut buat paket & aktifkan langganan.';
  refetch();
}

async function createPackage(supplierId: number) {
  await apiRequest(
    `/admin/suppliers/${supplierId}/packages`,
    { method: 'POST', body: JSON.stringify(pkgForm.value) },
    auth.token
  );
  activePanel.value = null;
  message.value = '2) Paket langganan dibuat. Klik "Aktifkan Paket" untuk mulai berlangganan.';
  refetch();
}

async function uploadManualProduct(supplierId: number) {
  await apiRequest(
    `/admin/suppliers/${supplierId}/manual-products`,
    { method: 'POST', body: JSON.stringify(manualForm.value) },
    auth.token
  );
  manualForm.value = { external_id: '', name: '', cost_price: 25000 };
  activePanel.value = null;
  message.value = 'Produk manual berhasil diupload ke supplier_products! Buka menu Products & Pricing untuk publikasi ke toko.';
  refetch();
}

async function updateCredentials(supplierId: number) {
  const payload: Record<string, unknown> = {
    api_key: credForm.value.api_key,
    sandbox: credForm.value.sandbox,
  };
  if (credForm.value.api_id) payload.api_id = credForm.value.api_id;
  if (credForm.value.username) payload.username = credForm.value.username;
  if (credForm.value.api_secret) payload.api_secret = credForm.value.api_secret;
  if (credForm.value.base_url) payload.base_url = credForm.value.base_url;

  await apiRequest(
    `/admin/suppliers/${supplierId}`,
    {
      method: 'PUT',
      body: JSON.stringify({ credentials: payload }),
    },
    auth.token
  );
  credForm.value = { api_id: '', username: '', api_key: '', api_secret: '', base_url: '', sandbox: true };
  activePanel.value = null;
  message.value = 'Kredensial API tersimpan & terenkripsi AES-256! Klik "Cek Health" lalu "↻ Sync Produk API".';
  refetch();
}

async function checkHealth(id: number) {
  const res = await apiRequest<{ data: { health_status: string; message: string } }>(
    `/admin/suppliers/${id}/health`,
    {},
    auth.token
  );
  message.value = `Health check: ${res.data.health_status} — ${res.data.message}`;
  refetch();
}

async function syncProducts(id: number) {
  message.value = '';
  try {
    const res = await apiRequest<{ data: { synced_count: number } }>(
      `/admin/suppliers/${id}/sync-products`,
      { method: 'POST' },
      auth.token
    );
    message.value = `Sinkronisasi selesai: ${res.data.synced_count} produk masuk supplier_products. Lanjut pilih di menu Products & Pricing.`;
    refetch();
  } catch (e: any) {
    message.value = e?.message || 'Gagal melakukan sinkronisasi produk supplier.';
  }
}

async function cancelSub(subscriptionId: number) {
  await apiRequest(
    '/admin/subscriptions/cancel',
    { method: 'POST', body: JSON.stringify({ subscription_id: subscriptionId }) },
    auth.token
  );
  message.value = 'Langganan dibatalkan — produk terkait otomatis dikunci untuk penjualan baru.';
  refetch();
}

async function subscribePackage(supplierId: number, packageId: number) {
  await apiRequest(
    '/admin/subscriptions/subscribe',
    { method: 'POST', body: JSON.stringify({ supplier_id: supplierId, package_id: packageId }) },
    auth.token
  );
  message.value = 'Langganan aktif! Sekarang Anda bisa Sync/Upload Produk lalu publikasikan di menu Products & Pricing.';
  refetch();
}

function rp(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
}
</script>

<template>
  <AdminLayout
    section="Operations"
    title="Suppliers & Langganan"
    subtitle="Alur: 1) Tambah Supplier → 2) Buat Paket & Aktifkan Langganan → 3) Sync API / Upload Produk Manual → 4) Publikasikan di Products"
  >
    <div v-if="message" class="p-3.5 rounded-xl border text-xs font-medium" style="background: var(--surface-2); border-color: var(--border); color: var(--text)">
      {{ message }}
    </div>

    <section class="unisell-card p-5 space-y-4">
      <h2 class="text-sm font-bold">1. Tambah Supplier Baru</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3" @submit.prevent="createSupplier">
        <input v-model="form.name" required class="unisell-input text-sm" placeholder="Nama Supplier (mis. DigiFlazz / Partner A)" />
        <input v-model="form.website" type="url" class="unisell-input text-sm" placeholder="https://supplier.example.com" />
        <select v-model="form.auth_method" class="unisell-input text-sm">
          <option value="vip_reseller">VIP-Reseller (Gratis &amp; Langsung Aktif)</option>
          <option value="digiflazz">Digiflazz H2H (Gratis Daftar &amp; Mode Testing)</option>
          <option value="sekalipay">SekaliPay API (Reseller / H2H)</option>
          <option value="api_key">API Key (Generic)</option>
          <option value="bearer">Bearer Token</option>
          <option value="manual">Manual (Upload Produk Manual)</option>
        </select>
        <input v-model="form.api_key" class="unisell-input text-sm" placeholder="API Key (opsional, terenkripsi)" />
        <input v-model="form.api_secret" type="password" class="unisell-input text-sm" placeholder="API Secret (terenkripsi di DB)" />
        <button type="submit" class="unisell-btn-primary text-sm">Simpan Supplier</button>
      </form>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div v-for="s in data?.data || []" :key="s.id" class="unisell-card p-5 space-y-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="text-base font-bold">{{ s.name }}</h3>
            <div class="text-xs font-mono-code mt-1" style="color: var(--muted)">
              Method: {{ s.auth_method }} · Produk Supplier: {{ s.supplier_products_count }} · Saldo: {{ rp(s.balance) }}
            </div>
          </div>
          <div class="flex gap-1.5 flex-wrap justify-end">
            <span class="unisell-badge" :style="{ color: s.health_status === 'healthy' ? 'var(--success)' : 'var(--warning)' }">
              ● {{ s.health_status }}
            </span>
            <span class="unisell-badge" :style="{ color: s.has_active_subscription ? 'var(--success)' : 'var(--danger)' }">
              {{ s.has_active_subscription ? 'Langganan Aktif' : 'Belum Berlangganan' }}
            </span>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button type="button" class="unisell-btn-secondary text-xs" @click="checkHealth(s.id)">
            Cek Health
          </button>

          <button
            v-if="s.auth_method !== 'manual'"
            type="button"
            class="unisell-btn-secondary text-xs"
            @click="activePanel = activePanel?.supplierId === s.id && activePanel.mode === 'credentials' ? null : { supplierId: s.id, mode: 'credentials' }"
          >
            🔑 Atur API Key
          </button>

          <button
            type="button"
            class="unisell-btn-secondary text-xs"
            @click="activePanel = activePanel?.supplierId === s.id && activePanel.mode === 'package' ? null : { supplierId: s.id, mode: 'package' }"
          >
            + Buat Paket
          </button>

          <button
            v-if="s.auth_method !== 'manual'"
            type="button"
            class="unisell-btn-secondary text-xs"
            @click="syncProducts(s.id)"
          >
            ↻ Sync Produk API
          </button>

          <button
            type="button"
            class="unisell-btn-secondary text-xs"
            @click="activePanel = activePanel?.supplierId === s.id && activePanel.mode === 'manual' ? null : { supplierId: s.id, mode: 'manual' }"
          >
            + Upload Produk Manual
          </button>

          <button
            v-if="s.active_subscription"
            type="button"
            class="unisell-btn-secondary text-xs"
            style="border-color: var(--danger); color: var(--danger)"
            @click="cancelSub(s.active_subscription.id)"
          >
            Batalkan Langganan
          </button>
          <button
            v-else-if="s.packages.length"
            type="button"
            class="unisell-btn-primary text-xs"
            @click="subscribePackage(s.id, s.packages[0].id)"
          >
            Aktifkan {{ s.packages[0].name }} ({{ rp(s.packages[0].price) }})
          </button>
        </div>

        <!-- Inline Form: Set Encrypted API Key (Digiflazz / SekaliPay / API Supplier) -->
        <form
          v-if="activePanel?.supplierId === s.id && activePanel.mode === 'credentials'"
          class="p-4 rounded-xl border space-y-3"
          style="background: var(--surface-2); border-color: var(--border)"
          @submit.prevent="updateCredentials(s.id)"
        >
          <div class="text-xs font-bold">Konfigurasi Kredensial API untuk {{ s.name }} (Terenkripsi AES-256)</div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <input
              v-if="s.auth_method === 'vip_reseller'"
              v-model="credForm.api_id"
              required
              class="unisell-input text-xs"
              placeholder="API ID VIP-Reseller (dari Member Area)"
            />
            <input
              v-if="s.auth_method === 'digiflazz'"
              v-model="credForm.username"
              required
              class="unisell-input text-xs"
              placeholder="Username Digiflazz (dari menu Atur Koneksi API)"
            />
            <input v-model="credForm.api_key" required type="password" class="unisell-input text-xs" placeholder="API Key" />
            <input
              v-if="s.auth_method !== 'digiflazz' && s.auth_method !== 'vip_reseller'"
              v-model="credForm.api_secret"
              type="password"
              class="unisell-input text-xs"
              placeholder="API Secret (opsional)"
            />
            <input
              v-model="credForm.base_url"
              type="url"
              class="unisell-input text-xs"
              :placeholder="s.auth_method === 'vip_reseller' ? 'https://vip-reseller.co.id/api (opsional)' : s.auth_method === 'digiflazz' ? 'https://api.digiflazz.com/v1 (opsional)' : 'https://sekalipay.com/api (opsional)'"
            />
            <label class="flex items-center gap-2 text-xs px-2 sm:col-span-2">
              <input v-model="credForm.sandbox" type="checkbox" />
              <span>Aktifkan Mode Testing / Sandbox (Gratis tanpa potong saldo asli)</span>
            </label>
          </div>
          <button type="submit" class="unisell-btn-primary text-xs">Simpan Kredensial Terenkripsi</button>
        </form>

        <!-- Inline Form: Create Package -->
        <form
          v-if="activePanel?.supplierId === s.id && activePanel.mode === 'package'"
          class="p-4 rounded-xl border space-y-3"
          style="background: var(--surface-2); border-color: var(--border)"
          @submit.prevent="createPackage(s.id)"
        >
          <div class="text-xs font-bold">Buat Paket Langganan untuk {{ s.name }}</div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <input v-model="pkgForm.name" required class="unisell-input text-xs" placeholder="Nama Paket" />
            <input v-model.number="pkgForm.price" required type="number" min="0" class="unisell-input text-xs" placeholder="Harga (Rp)" />
            <input v-model.number="pkgForm.period_days" required type="number" min="1" class="unisell-input text-xs" placeholder="Durasi (hari)" />
          </div>
          <button type="submit" class="unisell-btn-primary text-xs">Simpan Paket</button>
        </form>

        <!-- Inline Form: Upload Manual Product -->
        <form
          v-if="activePanel?.supplierId === s.id && activePanel.mode === 'manual'"
          class="p-4 rounded-xl border space-y-3"
          style="background: var(--surface-2); border-color: var(--border)"
          @submit.prevent="uploadManualProduct(s.id)"
        >
          <div class="text-xs font-bold">Upload Produk Manual ke {{ s.name }}</div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <input v-model="manualForm.external_id" required class="unisell-input text-xs font-mono-code" placeholder="SKU (mis. MAN-001)" />
            <input v-model="manualForm.name" required class="unisell-input text-xs" placeholder="Nama Produk" />
            <input v-model.number="manualForm.cost_price" required type="number" min="0" class="unisell-input text-xs" placeholder="Harga Modal (Rp)" />
          </div>
          <button type="submit" class="unisell-btn-primary text-xs">Upload ke Daftar Supplier</button>
        </form>
      </div>
    </section>
  </AdminLayout>
</template>
