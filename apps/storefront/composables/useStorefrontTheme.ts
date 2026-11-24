import { reactive } from 'vue';
import {
  type ThemeConfig,
  THEME_PRESETS,
  applyThemeToDocument,
  loadThemeConfig,
  saveThemeConfig,
} from '@unisell/ui';

const STORAGE_KEY = 'unisell-theme-storefront-v2';

const state = reactive<ThemeConfig>({
  presetId: 'dappr',
  mode: 'light',
  radius: 'xl',
  density: 'comfortable',
  customColors: {},
  savedThemes: [],
});

let initialized = false;

export function useStorefrontTheme() {
  function init() {
    if (initialized || typeof window === 'undefined') return;
    if (window.localStorage.getItem(STORAGE_KEY)) {
      Object.assign(state, loadThemeConfig(STORAGE_KEY));
    }
    applyThemeToDocument(state);
    initialized = true;
  }

  function toggleMode() {
    state.mode = state.mode === 'light' ? 'dark' : 'light';
    applyThemeToDocument(state);
    saveThemeConfig(STORAGE_KEY, state);
  }

  function setPreset(presetId: string) {
    state.presetId = presetId;
    applyThemeToDocument(state);
    saveThemeConfig(STORAGE_KEY, state);
  }

  return {
    config: state,
    presets: THEME_PRESETS,
    init,
    toggleMode,
    setPreset,
  };
}
