<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useThemeStore } from '../stores/theme';
import { ApiClientError } from '../lib/api';

const router = useRouter();
const auth = useAuthStore();
const theme = useThemeStore();

const email = ref('admin@unisell.local');
const password = ref('Admin12345!');
const errorMsg = ref('');
const loading = ref(false);

async function handleSubmit() {
  errorMsg.value = '';
  loading.value = true;
  try {
    await auth.login(email.value, password.value);
    router.push({ name: 'dashboard' });
  } catch (err) {
    if (err instanceof ApiClientError) {
      errorMsg.value = err.message;
    } else {
      errorMsg.value = 'Gagal terhubung ke server API.';
    }
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen grid grid-cols-1 lg:grid-cols-12">
    <!-- Left: Crisp Causal-style Sign-In Column -->
    <div
      class="lg:col-span-5 flex flex-col justify-between p-8 md:p-12 border-r"
      style="background: var(--surface); border-color: var(--border)"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div
            class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold font-mono-code"
            style="background: #3B49DF; color: #FFFFFF"
          >
            US
          </div>
          <span class="font-semibold tracking-tight">UniSell Digital</span>
        </div>

        <button
          type="button"
          class="unisell-btn-secondary text-xs py-1 px-2.5"
          @click="theme.toggleQuickMode()"
        >
          {{ theme.config.mode === 'light' ? 'Dark' : 'Light' }}
        </button>
      </div>

      <div class="max-w-sm w-full mx-auto space-y-6 my-12">
        <div class="space-y-1.5">
          <h1 class="text-2xl font-semibold tracking-tight">Sign in to Workspace</h1>
          <p class="text-sm" style="color: var(--muted)">
            Kelola supplier, model harga, dan laporan keuangan real-time.
          </p>
        </div>

        <div
          v-if="errorMsg"
          class="p-3 text-xs rounded-lg border"
          style="border-color: var(--danger); color: var(--danger); background: var(--surface-2)"
        >
          {{ errorMsg }}
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div class="space-y-1.5">
            <label class="block text-xs font-medium" style="color: var(--muted)" for="email">
              Work Email
            </label>
            <input
              id="email"
              v-model="email"
              type="email"
              required
              class="unisell-input"
              placeholder="admin@unisell.local"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-medium" style="color: var(--muted)" for="password">
              Password
            </label>
            <input
              id="password"
              v-model="password"
              type="password"
              required
              class="unisell-input"
              placeholder="••••••••"
            />
          </div>

          <button
            type="submit"
            class="w-full py-2.5 rounded-xl text-sm font-semibold cursor-pointer transition hover:opacity-90"
            style="background: #3B49DF; color: #FFFFFF"
            :disabled="loading"
          >
            {{ loading ? 'Memverifikasi...' : 'Continue to Dashboard →' }}
          </button>
        </form>
      </div>

      <div class="text-xs font-mono-code" style="color: var(--muted)">
        Default: admin@unisell.local / Admin12345!
      </div>
    </div>

    <!-- Right: Causal-inspired Live Financial Model Showcase -->
    <div class="hidden lg:flex lg:col-span-7 flex-col justify-center p-12 relative overflow-hidden">
      <div class="max-w-xl mx-auto w-full space-y-5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="unisell-badge font-mono-code" style="color: #4F5DFF">
              ● LIVE FINANCIAL MODEL
            </span>
            <span class="unisell-badge font-mono-code" style="color: #22C55E">
              Base Case
            </span>
          </div>
          <span class="text-xs font-mono-code" style="color: var(--muted)">Q4 · 2026</span>
        </div>

        <div class="unisell-card p-6 space-y-5 shadow-2xl">
          <div class="flex items-baseline justify-between">
            <div>
              <div class="text-xs uppercase tracking-wider" style="color: var(--muted)">
                Projected Net Profit
              </div>
              <div class="text-3xl font-bold font-mono-code mt-1" style="color: #4F5DFF">
                Rp18.450.000
              </div>
            </div>
            <span class="unisell-badge font-mono-code" style="color: #22C55E">
              ▲ +24.6% MoM
            </span>
          </div>

          <!-- Smooth SVG Area Chart -->
          <svg viewBox="0 0 460 150" class="w-full h-36 overflow-visible">
            <defs>
              <linearGradient id="loginAreaGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#4F5DFF" stop-opacity="0.35" />
                <stop offset="100%" stop-color="#4F5DFF" stop-opacity="0.02" />
              </linearGradient>
            </defs>
            <line x1="0" y1="30" x2="460" y2="30" stroke="var(--border)" stroke-dasharray="3 3" />
            <line x1="0" y1="75" x2="460" y2="75" stroke="var(--border)" stroke-dasharray="3 3" />
            <line x1="0" y1="120" x2="460" y2="120" stroke="var(--border)" stroke-dasharray="3 3" />
            <path
              d="M0 118 C60 110, 90 95, 140 82 C190 68, 240 76, 300 46 C360 22, 410 28, 460 12 L460 135 L0 135 Z"
              fill="url(#loginAreaGrad)"
            />
            <path
              d="M0 118 C60 110, 90 95, 140 82 C190 68, 240 76, 300 46 C360 22, 410 28, 460 12"
              fill="none"
              stroke="#4F5DFF"
              stroke-width="2.5"
            />
            <circle cx="300" cy="46" r="4.5" fill="#4F5DFF" />
            <circle cx="460" cy="12" r="4.5" fill="#22C55E" />
          </svg>

          <!-- Causal-style formula rows -->
          <div class="space-y-2 pt-2 border-t text-xs" style="border-color: var(--border)">
            <div class="flex items-center justify-between py-1">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" style="background: var(--primary)" />
                <span class="font-medium">Gross Revenue</span>
                <span class="unisell-formula-chip py-0.5 px-2 text-[11px]">= Orders × Sell Price</span>
              </div>
              <span class="font-mono-code font-semibold">Rp42.800.000</span>
            </div>
            <div class="flex items-center justify-between py-1">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" style="background: var(--warning)" />
                <span class="font-medium">Supplier Subscriptions</span>
                <span class="unisell-formula-chip py-0.5 px-2 text-[11px]">= Active Packages</span>
              </div>
              <span class="font-mono-code">-Rp450.000</span>
            </div>
            <div class="flex items-center justify-between py-1">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" style="background: var(--success)" />
                <span class="font-medium">Net Margin</span>
                <span class="unisell-formula-chip py-0.5 px-2 text-[11px]">= Revenue − Cost − Fee − Sub</span>
              </div>
              <span class="font-mono-code font-semibold" style="color: var(--success)">Rp18.450.000</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
