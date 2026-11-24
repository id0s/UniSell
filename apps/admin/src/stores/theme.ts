import { defineStore } from 'pinia';
import { reactive } from 'vue';
import {
  type AppearanceMode,
  type BorderRadiusPreset,
  type InterfaceDensity,
  type ThemeConfig,
  applyThemeToDocument,
  exportThemeConfig,
  importThemeConfig,
  loadThemeConfig,
  saveThemeConfig,
} from '@unisell/ui';
import { apiRequest } from '../lib/api';

const STORAGE_KEY = 'unisell-theme-admin-v2';

export const useThemeStore = defineStore('theme', () => {
  const loaded = loadThemeConfig(STORAGE_KEY);
  if (typeof window !== 'undefined' && !window.localStorage.getItem(STORAGE_KEY)) {
    loaded.presetId = 'dappr';
    loaded.mode = 'light';
    loaded.radius = 'xl';
  }
  const config = reactive<ThemeConfig>(loaded);

  function applyAndPersist(): void {
    applyThemeToDocument(config);
    saveThemeConfig(STORAGE_KEY, config);
  }

  function init(): void {
    applyThemeToDocument(config);
  }

  function setPreset(presetId: string): void {
    config.presetId = presetId;
    config.customColors = {};
    applyAndPersist();
  }

  function setMode(mode: AppearanceMode): void {
    config.mode = mode;
    applyAndPersist();
  }

  function toggleQuickMode(): void {
    config.mode = config.mode === 'light' ? 'dark' : 'light';
    applyAndPersist();
  }

  function setRadius(radius: BorderRadiusPreset): void {
    config.radius = radius;
    applyAndPersist();
  }

  function setDensity(density: InterfaceDensity): void {
    config.density = density;
    applyAndPersist();
  }

  function setCustomColor(key: 'primary' | 'accent' | 'bg' | 'surface', value: string): void {
    config.customColors = { ...(config.customColors ?? {}), [key]: value };
    applyAndPersist();
  }

  function resetCustomColors(): void {
    config.customColors = {};
    applyAndPersist();
  }

  function saveAsCustomTheme(name: string): void {
    const trimmed = name.trim();
    if (!trimmed) return;
    const entry = {
      id: `custom-${Date.now()}`,
      name: trimmed,
      config: {
        presetId: config.presetId,
        mode: config.mode,
        radius: config.radius,
        density: config.density,
        customColors: { ...(config.customColors ?? {}) },
      },
    };
    config.savedThemes = [...(config.savedThemes ?? []), entry];
    applyAndPersist();
  }

  function applySavedTheme(id: string): void {
    const found = config.savedThemes?.find((t) => t.id === id);
    if (!found) return;
    config.presetId = found.config.presetId;
    config.mode = found.config.mode;
    config.radius = found.config.radius;
    config.density = found.config.density;
    config.customColors = { ...(found.config.customColors ?? {}) };
    applyAndPersist();
  }

  function exportJson(): string {
    return exportThemeConfig(config);
  }

  function importJson(json: string): boolean {
    const parsed = importThemeConfig(json);
    if (!parsed) return false;
    Object.assign(config, parsed);
    applyAndPersist();
    return true;
  }

  async function syncToServer(token: string | null): Promise<void> {
    if (!token) return;
    await apiRequest(
      '/auth/theme',
      {
        method: 'PUT',
        body: JSON.stringify({
          presetId: config.presetId,
          mode: config.mode,
          radius: config.radius,
          density: config.density,
          customColors: config.customColors ?? {},
        }),
      },
      token
    );
  }

  return {
    config,
    init,
    setPreset,
    setMode,
    toggleQuickMode,
    setRadius,
    setDensity,
    setCustomColor,
    resetCustomColors,
    saveAsCustomTheme,
    applySavedTheme,
    exportJson,
    importJson,
    syncToServer,
  };
});
