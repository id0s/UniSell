<script setup lang="ts">
import { computed, ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import AdminLayout from '../components/AdminLayout.vue';
import { useAuthStore } from '../stores/auth';
import { useThemeStore } from '../stores/theme';
import { apiRequest } from '../lib/api';

const auth = useAuthStore();
const theme = useThemeStore();
const scenario = ref<'actual' | 'base' | 'optimis'>('actual');
const hoveredPointIdx = ref<number | null>(null);

const isDark = computed(() => theme.config.mode === 'dark');

interface ReportResponse {
  data: {
    financial: {
      omzet_idr: number;
      modal_idr: number;
      biaya_gateway_idr: number;
      biaya_langganan_idr: number;
      laba_bersih_idr: number;
    };
    operations: {
      total_orders: number;
      delivered_orders: number;
      manual_review_orders: number;
      failure_rate_percent: number;
    };
    top_products: Array<{
      id: number;
      name: string;
      total_qty: number;
      revenue_idr: number;
      gross_profit_idr: number;
    }>;
    supplier_evaluations: Array<{
      supplier_id: number;
      name: string;
      health_status: string;
      total_orders: number;
      success_orders: number;
      failed_orders: number;
      failure_rate_percent: number;
      subscription_cost_idr: number;
      gross_profit_idr: number;
      net_contribution_idr: number;
      worth_keeping: boolean;
      verdict: string;
    }>;
  };
}

interface OrderRow {
  id: number;
  order_no: string;
  channel: string;
  status: string;
  total: number;
  guest_name?: string;
  items: Array<{ qty: number; product?: { name: string } }>;
  created_at: string;
}

const { data, isLoading, refetch } = useQuery({
  queryKey: ['admin-reports'],
  queryFn: () => apiRequest<ReportResponse>('/admin/reports', {}, auth.token),
});

const { data: ordersData, refetch: refetchOrders } = useQuery({
  queryKey: ['admin-recent-orders'],
  queryFn: () => apiRequest<{ data: OrderRow[] }>('/admin/orders', {}, auth.token),
});

function refreshAll() {
  refetch();
  refetchOrders();
}

const multiplier = computed(() =>
  scenario.value === 'actual' ? 1 : scenario.value === 'base' ? 1.45 : 2.2
);

const fin = computed(() => {
  const f = data.value?.data.financial;
  if (!f) {
    return { omzet: 0, modal: 0, gateway: 0, langganan: 0, net: 0, marginPct: 0 };
  }
  const omzet = Math.round(f.omzet_idr * multiplier.value);
  const modal = Math.round(f.modal_idr * multiplier.value);
  const gateway = Math.round(f.biaya_gateway_idr * multiplier.value);
  const langganan = f.biaya_langganan_idr;
  const net = omzet - modal - gateway - langganan;
  const marginPct = omzet > 0 ? Math.round((net / omzet) * 1000) / 10 : 0;
  return { omzet, modal, gateway, langganan, net, marginPct };
});

const months = ['Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov'];
const points = computed(() => {
  const base = Math.max(50000, fin.value.omzet);
  const weights =
    scenario.value === 'actual'
      ? [0.46, 0.58, 0.52, 0.74, 0.88, 1.0]
      : scenario.value === 'base'
        ? [0.48, 0.64, 0.72, 0.92, 1.16, 1.45]
        : [0.5, 0.74, 0.98, 1.35, 1.78, 2.2];
  const maxV = Math.max(...weights) * base;
  return weights.map((w, idx) => {
    const x = Math.round(24 + (idx / (weights.length - 1)) * 512);
    const y = Math.round(148 - ((base * w) / maxV) * 116);
    return { month: months[idx], x, y, val: Math.round(base * w) };
  });
});

const curvePath = computed(() => {
  const pts = points.value;
  if (pts.length < 2) return '';
  let d = `M ${pts[0].x} ${pts[0].y}`;
  for (let i = 0; i < pts.length - 1; i++) {
    const cx = Math.round((pts[i].x + pts[i + 1].x) / 2);
    d += ` C ${cx} ${pts[i].y}, ${cx} ${pts[i + 1].y}, ${pts[i + 1].x} ${pts[i + 1].y}`;
  }
  return d;
});

const areaPath = computed(() => {
  const pts = points.value;
  if (!pts.length) return '';
  return `${curvePath.value} L ${pts[pts.length - 1].x} 162 L ${pts[0].x} 162 Z`;
});

// Pure matte monochrome tones in Dark Mode, crisp Dappr tones in Light Mode
const donutSlices = computed(() => {
  const total = Math.max(1, fin.value.modal + fin.value.gateway + fin.value.langganan + Math.max(0, fin.value.net));
  const palette = isDark.value
    ? ['#EDEDEF', '#71717A', '#3F3F46', '#22C55E']
    : ['#3B49DF', '#F59E0B', '#A855F7', '#22C55E'];

  const items = [
    { label: 'Modal Supplier', value: fin.value.modal, color: palette[0] },
    { label: 'Langganan', value: fin.value.langganan, color: palette[1] },
    { label: 'Gateway Fee', value: fin.value.gateway, color: palette[2] },
    { label: 'Laba Bersih', value: Math.max(0, fin.value.net), color: palette[3] },
  ];
  const C = 289;
  let offset = 0;
  return items.map((item) => {
    const pct = Math.round((item.value / total) * 100);
    const dash = (item.value / total) * C;
    const slice = { ...item, pct, dash, offset: -offset };
    offset += dash;
    return slice;
  });
});

const chartStroke = computed(() => (isDark.value ? '#EDEDEF' : 'var(--primary)'));

function rp(value: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value);
}
</script>

<template>
  <AdminLayout
    title="Dashboard"
    subtitle="Ringkasan omzet, laba bersih, transaksi terbaru, dan performa supplier"
  >
    <template #actions>
      <div class="p-1 rounded-2xl flex items-center gap-1 border" style="background: var(--surface-2); border-color: var(--border)">
        <button
          v-for="sc in ([
            { id: 'actual', label: 'Actual' },
            { id: 'base', label: 'Base +45%' },
            { id: 'optimis', label: 'Optimis' },
          ] as const)"
          :key="sc.id"
          type="button"
          class="px-3 py-1.5 rounded-xl text-xs font-medium transition cursor-pointer"
          :style="
            scenario === sc.id
              ? { background: 'var(--primary)', color: 'var(--primary-fg)' }
              : { color: 'var(--muted)' }
          "
          @click="scenario = sc.id"
        >
          {{ sc.label }}
        </button>
      </div>

      <button
        type="button"
        class="w-9 h-9 rounded-full flex items-center justify-center text-sm cursor-pointer border"
        style="background: var(--surface-2); color: var(--text); border-color: var(--border)"
        title="Refresh"
        @click="refreshAll()"
      >
        ↻
      </button>
    </template>

    <div v-if="isLoading" class="p-8 text-sm rounded-2xl" style="background: var(--surface-2); color: var(--muted)">
      Memuat data dashboard...
    </div>

    <template v-else-if="data">
      <!-- 4 Clean Matte KPI Cards -->
      <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="dash-fade-up dash-delay-1 p-6 rounded-2xl border space-y-3" style="background: var(--surface); border-color: var(--border)">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium" style="color: var(--muted)">Total Omzet</span>
            <span class="w-2 h-2 rounded-full" style="background: var(--primary)" />
          </div>
          <div class="text-2xl font-bold font-mono-code tracking-tight">{{ rp(fin.omzet) }}</div>
          <div class="text-xs font-medium" style="color: var(--success)">+18.4% vs periode lalu</div>
        </div>

        <div class="dash-fade-up dash-delay-2 p-6 rounded-2xl border space-y-3" style="background: var(--surface); border-color: var(--border)">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium" style="color: var(--muted)">Laba Bersih</span>
            <span class="w-2 h-2 rounded-full" :style="{ background: fin.net >= 0 ? 'var(--success)' : 'var(--danger)' }" />
          </div>
          <div
            class="text-2xl font-bold font-mono-code tracking-tight"
            :style="{ color: fin.net >= 0 ? 'var(--success)' : 'var(--danger)' }"
          >
            {{ rp(fin.net) }}
          </div>
          <div class="text-xs" style="color: var(--muted)">Margin bersih {{ fin.marginPct }}%</div>
        </div>

        <div class="dash-fade-up dash-delay-3 p-6 rounded-2xl border space-y-3" style="background: var(--surface); border-color: var(--border)">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium" style="color: var(--muted)">Pesanan Terkirim</span>
            <span
              class="px-2 py-0.5 rounded-md text-[11px] font-bold font-mono-code"
              style="background: var(--primary); color: var(--primary-fg)"
            >
              {{ data.data.operations.delivered_orders }}
            </span>
          </div>
          <div class="text-2xl font-bold font-mono-code tracking-tight">
            {{ data.data.operations.delivered_orders }}
            <span class="text-sm font-normal" style="color: var(--muted)">/ {{ data.data.operations.total_orders }}</span>
          </div>
          <div class="text-xs" style="color: var(--muted)">
            Fail {{ data.data.operations.failure_rate_percent }}% · Review {{ data.data.operations.manual_review_orders }}
          </div>
        </div>

        <div class="dash-fade-up dash-delay-4 p-6 rounded-2xl border space-y-3" style="background: var(--surface); border-color: var(--border)">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium" style="color: var(--muted)">Biaya Langganan</span>
            <span class="w-2 h-2 rounded-full" style="background: var(--muted)" />
          </div>
          <div class="text-2xl font-bold font-mono-code tracking-tight">{{ rp(fin.langganan) }}</div>
          <div class="text-xs" style="color: var(--muted)">{{ data.data.supplier_evaluations.length }} supplier aktif</div>
        </div>
      </section>

      <!-- Main Analytics Row (8 / 4) with Animated SVG Charts -->
      <section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        <div class="xl:col-span-8 p-6 rounded-2xl border space-y-5" style="background: var(--surface); border-color: var(--border)">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="text-base font-bold">Revenue &amp; Cashflow Trend</h2>
              <p class="text-xs mt-0.5" style="color: var(--muted)">
                {{ hoveredPointIdx !== null ? `${points[hoveredPointIdx].month}: ${rp(points[hoveredPointIdx].val)}` : 'Arahkan kursor ke titik grafik untuk detail' }}
              </p>
            </div>
            <div class="flex items-center gap-4 text-xs font-mono-code" style="color: var(--muted)">
              <span>Modal: <strong style="color: var(--text)">{{ rp(fin.modal) }}</strong></span>
              <span>Fee: <strong style="color: var(--text)">{{ rp(fin.gateway) }}</strong></span>
            </div>
          </div>

          <svg :key="scenario + String(isDark)" viewBox="0 0 560 185" class="w-full h-52 overflow-visible">
            <defs>
              <linearGradient id="matteAreaGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="chartStroke" :stop-opacity="isDark ? '0.16' : '0.2'" />
                <stop offset="100%" :stop-color="chartStroke" stop-opacity="0.0" />
              </linearGradient>
            </defs>
            <line x1="20" y1="36" x2="540" y2="36" stroke="var(--border)" stroke-dasharray="4 4" />
            <line x1="20" y1="98" x2="540" y2="98" stroke="var(--border)" stroke-dasharray="4 4" />
            <line x1="20" y1="162" x2="540" y2="162" stroke="var(--border)" />

            <path class="dash-area-fade" :d="areaPath" fill="url(#matteAreaGrad)" />
            <path
              class="dash-draw-line"
              :d="curvePath"
              fill="none"
              :stroke="chartStroke"
              stroke-width="2.5"
              stroke-linecap="round"
            />

            <g
              v-for="(pt, idx) in points"
              :key="pt.month"
              class="cursor-pointer"
              @mouseenter="hoveredPointIdx = idx"
              @mouseleave="hoveredPointIdx = null"
            >
              <circle
                :cx="pt.x"
                :cy="pt.y"
                :r="hoveredPointIdx === idx ? 7 : 4.5"
                fill="var(--surface)"
                :stroke="chartStroke"
                stroke-width="2.2"
                class="transition-all duration-150"
              />
              <text
                v-if="hoveredPointIdx === idx"
                :x="pt.x"
                :y="pt.y - 12"
                text-anchor="middle"
                fill="var(--text)"
                font-size="11"
                font-family="JetBrains Mono, monospace"
                font-weight="600"
              >
                {{ rp(pt.val) }}
              </text>
              <text :x="pt.x" y="180" text-anchor="middle" fill="var(--muted)" font-size="11">
                {{ pt.month }}
              </text>
            </g>
          </svg>
        </div>

        <div class="xl:col-span-4 p-6 rounded-2xl border flex flex-col justify-between gap-5" style="background: var(--surface); border-color: var(--border)">
          <div>
            <h2 class="text-base font-bold">Cost &amp; Margin Split</h2>
            <p class="text-xs mt-0.5" style="color: var(--muted)">Komposisi biaya terhadap omzet</p>
          </div>

          <div class="flex justify-center">
            <div class="relative w-36 h-36 flex items-center justify-center">
              <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
                <circle cx="60" cy="60" r="46" fill="none" stroke="var(--surface-2)" stroke-width="11" />
                <circle
                  v-for="slice in donutSlices"
                  :key="slice.label"
                  class="dash-donut-ring"
                  cx="60"
                  cy="60"
                  r="46"
                  fill="none"
                  :stroke="slice.color"
                  stroke-width="11"
                  stroke-linecap="round"
                  :stroke-dasharray="`${slice.dash} 289`"
                  :stroke-dashoffset="slice.offset"
                />
              </svg>
              <div class="absolute text-center">
                <div class="text-lg font-bold font-mono-code">{{ fin.marginPct }}%</div>
                <div class="text-[10px]" style="color: var(--muted)">Net Margin</div>
              </div>
            </div>
          </div>

          <div class="space-y-2 text-xs">
            <div v-for="slice in donutSlices" :key="slice.label" class="flex items-center justify-between">
              <span class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full" :style="{ background: slice.color }" />
                <span style="color: var(--muted)">{{ slice.label }}</span>
              </span>
              <span class="font-mono-code font-medium">{{ rp(slice.value) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Bottom Clean Row (7 / 5) -->
      <section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        <div class="xl:col-span-7 rounded-2xl border overflow-hidden" style="background: var(--surface); border-color: var(--border)">
          <div class="px-6 py-4 border-b flex items-center justify-between" style="border-color: var(--border)">
            <h2 class="text-base font-bold">Recent Orders</h2>
            <RouterLink to="/orders" class="text-xs font-semibold no-underline" style="color: var(--primary)">
              Buka Orders Inbox →
            </RouterLink>
          </div>
          <div class="divide-y" style="border-color: var(--border)">
            <div
              v-for="o in (ordersData?.data || []).slice(0, 5)"
              :key="o.id"
              class="px-6 py-3.5 flex items-center justify-between gap-3 text-xs"
            >
              <div class="flex items-center gap-3 min-w-0">
                <span
                  class="w-2 h-2 rounded-full shrink-0"
                  :style="{ background: o.status === 'delivered' ? 'var(--success)' : 'var(--warning)' }"
                />
                <div class="min-w-0">
                  <div class="font-bold truncate">{{ o.guest_name || o.order_no }}</div>
                  <div class="truncate" style="color: var(--muted)">
                    {{ o.items[0]?.product?.name }} · <span class="font-mono-code">{{ o.order_no }}</span>
                  </div>
                </div>
              </div>
              <div class="text-right shrink-0">
                <div class="font-mono-code font-bold">{{ rp(o.total) }}</div>
                <div class="text-[11px] font-mono-code" style="color: var(--muted)">{{ o.status }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="xl:col-span-5 p-6 rounded-2xl border space-y-4" style="background: var(--surface); border-color: var(--border)">
          <div class="flex items-center justify-between">
            <h2 class="text-base font-bold">Supplier Performance</h2>
            <RouterLink to="/suppliers" class="text-xs font-semibold no-underline" style="color: var(--primary)">
              Kelola →
            </RouterLink>
          </div>
          <div class="space-y-3">
            <div
              v-for="s in data.data.supplier_evaluations"
              :key="s.supplier_id"
              class="p-4 rounded-xl border space-y-1.5"
              style="background: var(--surface-2); border-color: var(--border)"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-bold">{{ s.name }}</span>
                <span
                  class="px-2 py-0.5 rounded-md text-[11px] font-semibold"
                  :style="{
                    background: s.worth_keeping ? 'rgba(34,197,94,0.12)' : 'rgba(239,68,68,0.12)',
                    color: s.worth_keeping ? 'var(--success)' : 'var(--danger)',
                  }"
                >
                  {{ s.verdict }}
                </span>
              </div>
              <div class="flex items-center justify-between text-xs font-mono-code" style="color: var(--muted)">
                <span>{{ s.success_orders }}/{{ s.total_orders }} sukses</span>
                <span>Net {{ rp(s.net_contribution_idr) }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>
    </template>
  </AdminLayout>
</template>
