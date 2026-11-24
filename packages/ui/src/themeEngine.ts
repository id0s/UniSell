import {
  type AppearanceMode,
  type BorderRadiusPreset,
  type InterfaceDensity,
  RADIUS_VALUES,
  type ThemeTokens,
  getPresetById,
} from './presets';

export interface CustomThemeEntry {
  id: string;
  name: string;
  config: Omit<ThemeConfig, 'savedThemes'>;
}

export interface ThemeConfig {
  presetId: string;
  mode: AppearanceMode;
  radius: BorderRadiusPreset;
  density: InterfaceDensity;
  customColors?: Partial<Pick<ThemeTokens, 'primary' | 'accent' | 'bg' | 'surface'>>;
  savedThemes?: CustomThemeEntry[];
}

export const DEFAULT_THEME_CONFIG: ThemeConfig = {
  presetId: 'neon',
  mode: 'dark',
  radius: 'lg',
  density: 'comfortable',
  customColors: {},
  savedThemes: [],
};

export function resolveEffectiveMode(mode: AppearanceMode): 'dark' | 'light' {
  if (mode === 'dark' || mode === 'light') {
    return mode;
  }
  if (typeof window !== 'undefined' && window.matchMedia) {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  return 'dark';
}

export function resolveTokens(config: ThemeConfig): ThemeTokens {
  const preset = getPresetById(config.presetId);
  const effectiveMode = resolveEffectiveMode(config.mode);
  const base = preset[effectiveMode];
  return {
    ...base,
    ...(config.customColors ?? {}),
  };
}

export function applyThemeToDocument(config: ThemeConfig): void {
  if (typeof document === 'undefined') return;

  const root = document.documentElement;
  const effectiveMode = resolveEffectiveMode(config.mode);
  const tokens = resolveTokens(config);

  root.classList.toggle('dark', effectiveMode === 'dark');
  root.setAttribute('data-theme-preset', config.presetId);
  root.setAttribute('data-theme-mode', effectiveMode);
  root.setAttribute('data-density', config.density);

  root.style.setProperty('--bg', tokens.bg);
  root.style.setProperty('--surface', tokens.surface);
  root.style.setProperty('--surface-2', tokens.surface2);
  root.style.setProperty('--border', tokens.border);
  root.style.setProperty('--text', tokens.text);
  root.style.setProperty('--muted', tokens.muted);
  root.style.setProperty('--primary', tokens.primary);
  root.style.setProperty('--primary-fg', tokens.primaryFg);
  root.style.setProperty('--accent', tokens.accent);
  root.style.setProperty('--accent-fg', tokens.accentFg);
  root.style.setProperty('--success', tokens.success);
  root.style.setProperty('--warning', tokens.warning);
  root.style.setProperty('--danger', tokens.danger);
  root.style.setProperty('--glow-primary', effectiveMode === 'dark' ? tokens.glowPrimary : 'none');
  root.style.setProperty('--glow-accent', effectiveMode === 'dark' ? tokens.glowAccent : 'none');
  root.style.setProperty('--radius', RADIUS_VALUES[config.radius] ?? RADIUS_VALUES.lg);
}

export function loadThemeConfig(storageKey: string): ThemeConfig {
  if (typeof window === 'undefined') return { ...DEFAULT_THEME_CONFIG };
  try {
    const raw = window.localStorage.getItem(storageKey);
    if (!raw) return { ...DEFAULT_THEME_CONFIG };
    const parsed = JSON.parse(raw) as Partial<ThemeConfig>;
    return {
      ...DEFAULT_THEME_CONFIG,
      ...parsed,
      customColors: parsed.customColors ?? {},
      savedThemes: parsed.savedThemes ?? [],
    };
  } catch {
    return { ...DEFAULT_THEME_CONFIG };
  }
}

export function saveThemeConfig(storageKey: string, config: ThemeConfig): void {
  if (typeof window === 'undefined') return;
  window.localStorage.setItem(storageKey, JSON.stringify(config));
}

export function exportThemeConfig(config: ThemeConfig): string {
  return JSON.stringify(
    {
      presetId: config.presetId,
      mode: config.mode,
      radius: config.radius,
      density: config.density,
      customColors: config.customColors ?? {},
    },
    null,
    2
  );
}

export function importThemeConfig(json: string): Partial<ThemeConfig> | null {
  try {
    const parsed = JSON.parse(json) as Partial<ThemeConfig>;
    if (!parsed || typeof parsed !== 'object') return null;
    return {
      presetId: typeof parsed.presetId === 'string' ? parsed.presetId : 'neon',
      mode: parsed.mode === 'light' || parsed.mode === 'dark' || parsed.mode === 'system' ? parsed.mode : 'dark',
      radius: parsed.radius && parsed.radius in RADIUS_VALUES ? parsed.radius : 'lg',
      density:
        parsed.density === 'compact' || parsed.density === 'comfortable' || parsed.density === 'spacious'
          ? parsed.density
          : 'comfortable',
      customColors: parsed.customColors && typeof parsed.customColors === 'object' ? parsed.customColors : {},
    };
  } catch {
    return null;
  }
}
