<script setup lang="ts">
import { ref } from 'vue';
import {
  THEME_PRESETS,
  type AppearanceMode,
  type BorderRadiusPreset,
  type InterfaceDensity,
} from '@unisell/ui';
import { useThemeStore } from '../stores/theme';
import { useAuthStore } from '../stores/auth';

const theme = useThemeStore();
const auth = useAuthStore();

const customThemeName = ref('');
const importText = ref('');
const statusMessage = ref('');

const modes: AppearanceMode[] = ['dark', 'light', 'system'];
const radii: BorderRadiusPreset[] = ['sm', 'md', 'lg', 'xl'];
const densities: InterfaceDensity[] = ['compact', 'comfortable', 'spacious'];

function handleSaveCustom() {
  if (!customThemeName.value.trim()) return;
  theme.saveAsCustomTheme(customThemeName.value);
  customThemeName.value = '';
  statusMessage.value = 'Custom theme disimpan ke lokal.';
}

async function handleSyncServer() {
  try {
    await theme.syncToServer(auth.token);
    statusMessage.value = 'Preferensi tema berhasil disimpan ke akun server.';
  } catch {
    statusMessage.value = 'Gagal menyimpan preferensi ke server.';
  }
}

function handleCopyExport() {
  const json = theme.exportJson();
  navigator.clipboard?.writeText(json);
  statusMessage.value = 'Konfigurasi JSON tema disalin ke clipboard.';
}

function handleImport() {
  const ok = theme.importJson(importText.value);
  statusMessage.value = ok
    ? 'Konfigurasi tema berhasil diimpor.'
    : 'Format JSON tidak valid.';
}
</script>

<template>
  <div class="min-h-screen p-8 max-w-6xl mx-auto space-y-6">
    <header class="flex items-center justify-between">
      <div>
        <span class="unisell-badge font-mono-code" style="color: var(--primary)">
          UNISELL THEME SYSTEM v1.0
        </span>
        <h1 class="text-2xl font-bold mt-2">Theme &amp; Appearance Manager</h1>
        <p class="text-sm" style="color: var(--muted)">
          Preset Neon Dark default + 8 preset profesional, custom colors, radius, dan density.
        </p>
      </div>
      <div class="flex gap-2">
        <button type="button" class="unisell-btn-primary text-sm" @click="handleSyncServer">
          Simpan ke Akun
        </button>
        <RouterLink to="/" class="unisell-btn-secondary text-sm">
          Kembali ke Dashboard
        </RouterLink>
      </div>
    </header>

    <div
      v-if="statusMessage"
      class="unisell-card p-3 text-sm"
      style="border-color: var(--primary); color: var(--primary)"
    >
      {{ statusMessage }}
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <section class="unisell-card p-5 space-y-4">
          <h2 class="font-semibold">1. Color Presets (9 Bawaan)</h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <button
              v-for="preset in THEME_PRESETS"
              :key="preset.id"
              type="button"
              class="text-left p-3 rounded-lg border transition"
              :style="{
                background: 'var(--surface-2)',
                borderColor: theme.config.presetId === preset.id ? 'var(--primary)' : 'var(--border)',
              }"
              @click="theme.setPreset(preset.id)"
            >
              <div class="flex items-center gap-2">
                <span
                  class="w-3.5 h-3.5 rounded-full inline-block"
                  :style="{ background: preset.dark.primary }"
                />
                <span
                  class="w-3.5 h-3.5 rounded-full inline-block"
                  :style="{ background: preset.dark.accent }"
                />
                <span class="text-sm font-semibold">{{ preset.name }}</span>
              </div>
              <div class="text-xs mt-1 line-clamp-2" style="color: var(--muted)">
                {{ preset.description }}
              </div>
            </button>
          </div>
        </section>

        <section class="unisell-card p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="space-y-2">
            <h3 class="text-sm font-semibold">Appearance Mode</h3>
            <div class="flex gap-2">
              <button
                v-for="m in modes"
                :key="m"
                type="button"
                class="unisell-btn-secondary text-xs capitalize"
                :style="theme.config.mode === m ? { borderColor: 'var(--primary)', color: 'var(--primary)' } : {}"
                @click="theme.setMode(m)"
              >
                {{ m }}
              </button>
            </div>
          </div>

          <div class="space-y-2">
            <h3 class="text-sm font-semibold">Border Radius</h3>
            <div class="flex gap-2">
              <button
                v-for="r in radii"
                :key="r"
                type="button"
                class="unisell-btn-secondary text-xs uppercase"
                :style="theme.config.radius === r ? { borderColor: 'var(--primary)', color: 'var(--primary)' } : {}"
                @click="theme.setRadius(r)"
              >
                {{ r }}
              </button>
            </div>
          </div>

          <div class="space-y-2">
            <h3 class="text-sm font-semibold">Interface Density</h3>
            <div class="flex gap-2 flex-wrap">
              <button
                v-for="d in densities"
                :key="d"
                type="button"
                class="unisell-btn-secondary text-xs capitalize"
                :style="theme.config.density === d ? { borderColor: 'var(--primary)', color: 'var(--primary)' } : {}"
                @click="theme.setDensity(d)"
              >
                {{ d }}
              </button>
            </div>
          </div>
        </section>

        <section class="unisell-card p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="font-semibold">2. Custom Colors &amp; Save Theme</h2>
            <button type="button" class="unisell-btn-secondary text-xs" @click="theme.resetCustomColors()">
              Reset Custom
            </button>
          </div>
          <div class="flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2">
              <span>Primary</span>
              <input
                type="color"
                :value="theme.config.customColors?.primary || '#00F0FF'"
                @input="theme.setCustomColor('primary', ($event.target as HTMLInputElement).value)"
              />
            </label>
            <label class="flex items-center gap-2">
              <span>Accent</span>
              <input
                type="color"
                :value="theme.config.customColors?.accent || '#FF2BD6'"
                @input="theme.setCustomColor('accent', ($event.target as HTMLInputElement).value)"
              />
            </label>
          </div>

          <div class="flex gap-2">
            <input
              v-model="customThemeName"
              type="text"
              class="unisell-input text-sm"
              placeholder="Nama tema kustom (mis. Client Branding)"
            />
            <button type="button" class="unisell-btn-secondary text-sm shrink-0" @click="handleSaveCustom">
              Save Theme
            </button>
          </div>

          <div v-if="theme.config.savedThemes?.length" class="flex flex-wrap gap-2">
            <button
              v-for="saved in theme.config.savedThemes"
              :key="saved.id"
              type="button"
              class="unisell-badge cursor-pointer"
              @click="theme.applySavedTheme(saved.id)"
            >
              {{ saved.name }}
            </button>
          </div>
        </section>
      </div>

      <aside class="space-y-6">
        <div class="unisell-card p-5 space-y-4">
          <div class="text-xs font-mono-code uppercase" style="color: var(--muted)">
            Live Preview
          </div>
          <div class="unisell-card-elevated p-4 space-y-3">
            <div class="text-sm font-bold" style="color: var(--primary)">UniSell</div>
            <div class="text-xs" style="color: var(--muted)">Dashboard Overview</div>
            <div class="text-2xl font-bold font-mono-code">Rp12.500.000</div>
            <div class="text-xs" style="color: var(--success)">+12.8% this month</div>
            <button type="button" class="unisell-btn-primary w-full text-sm">
              Primary Button
            </button>
          </div>
        </div>

        <div class="unisell-card p-5 space-y-3">
          <h3 class="text-sm font-semibold">Export / Import JSON</h3>
          <button type="button" class="unisell-btn-secondary w-full text-xs" @click="handleCopyExport">
            Salin Konfigurasi JSON
          </button>
          <textarea
            v-model="importText"
            rows="3"
            class="unisell-input text-xs font-mono-code"
            placeholder="Tempel JSON konfigurasi tema..."
          />
          <button type="button" class="unisell-btn-secondary w-full text-xs" @click="handleImport">
            Import Konfigurasi
          </button>
        </div>
      </aside>
    </div>
  </div>
</template>
