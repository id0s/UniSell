<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { useAuthStore } from '../stores/auth';
import { useThemeStore } from '../stores/theme';
import { apiRequest } from '../lib/api';

defineProps<{
  title: string;
  subtitle?: string;
  section?: string;
  flushMain?: boolean;
}>();

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const theme = useThemeStore();

const drawerHovered = ref(false);
const drawerPinned = ref(false);
const mobileMenuOpen = ref(false);
let closeTimer: ReturnType<typeof setTimeout> | null = null;

const isDrawerOpen = computed(() => drawerPinned.value || drawerHovered.value);
const isDark = computed(() => theme.config.mode === 'dark');

function openDrawer() {
  if (closeTimer) {
    clearTimeout(closeTimer);
    closeTimer = null;
  }
  drawerHovered.value = true;
}

function scheduleCloseDrawer() {
  if (drawerPinned.value) return;
  closeTimer = setTimeout(() => {
    drawerHovered.value = false;
  }, 150);
}

const showQuickNav = ref(false);
const quickQuery = ref('');

const { data: dashSummary } = useQuery({
  queryKey: ['admin-nav-counts'],
  queryFn: () =>
    apiRequest<{
      data: {
        summary: {
          orders_count: number;
          suppliers_count: number;
          products_count: number;
          manual_review_count: number;
        };
      };
    }>('/admin/dashboard', {}, auth.token),
});

const ordersCount = computed(() => dashSummary.value?.data?.summary?.orders_count ?? 0);
const reviewCount = computed(() => dashSummary.value?.data?.summary?.manual_review_count ?? 0);
const suppliersCount = computed(() => dashSummary.value?.data?.summary?.suppliers_count ?? 0);
const productsCount = computed(() => dashSummary.value?.data?.summary?.products_count ?? 0);

const railItems = [
  { to: '/', label: 'Dashboard', icon: 'home' },
  { to: '/orders', label: 'Orders Inbox', icon: 'inbox' },
  { to: '/products', label: 'Products & Pricing', icon: 'grid' },
  { to: '/suppliers', label: 'Suppliers & Subs', icon: 'bank' },
  { to: '/appearance', label: 'Theme Manager', icon: 'palette' },
];

const subMenuItems = computed(() => [
  { to: '/', label: 'Dashboard', icon: 'home', badge: null },
  { to: '/orders', label: 'Orders Inbox', icon: 'inbox', badge: ordersCount.value || null },
  { to: '/products', label: 'Products & Pricing', icon: 'grid', badge: productsCount.value || null },
  { to: '/suppliers', label: 'Suppliers & Subs', icon: 'bank', badge: suppliersCount.value || null },
  { to: '/appearance', label: 'Theme Manager', icon: 'palette', badge: null },
]);

function handleKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    showQuickNav.value = !showQuickNav.value;
    quickQuery.value = '';
  } else if (e.key === 'Escape') {
    showQuickNav.value = false;
  }
}

onMounted(() => {
  if (!auth.user) {
    auth.fetchProfile();
  }
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  if (closeTimer) clearTimeout(closeTimer);
});

function goQuick(path: string) {
  showQuickNav.value = false;
  router.push(path);
}

async function handleLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}
</script>

<template>
  <div class="min-h-screen w-full flex flex-col md:flex-row relative" style="background: var(--bg); color: var(--text)">
    <!-- Mobile Top Bar (< md) -->
    <header
      class="md:hidden flex items-center justify-between px-4 h-14 border-b sticky top-0 z-40"
      style="background: var(--surface); border-color: var(--border)"
    >
      <div class="flex items-center gap-2.5">
        <button
          type="button"
          class="w-9 h-9 rounded-xl flex items-center justify-center text-sm font-bold cursor-pointer"
          style="background: #18181B; color: #FFFFFF"
          @click="mobileMenuOpen = !mobileMenuOpen"
        >
          ☰
        </button>
        <span class="font-bold text-sm tracking-tight">UniSell</span>
      </div>
      <span class="text-xs font-medium" style="color: var(--muted)">{{ title }}</span>
    </header>

    <!-- Mobile Slide-Over Drawer matching Desktop Dappr Rail + Lavender Sub-Menu (< md) -->
    <div
      v-if="mobileMenuOpen"
      class="md:hidden fixed inset-0 z-50 flex p-3 gap-2"
      style="background: rgba(0, 0, 0, 0.55)"
      @click.self="mobileMenuOpen = false"
    >
      <!-- Mini Obsidian Rail (identical to desktop) -->
      <div
        class="w-[62px] rounded-[22px] py-5 flex flex-col items-center justify-between shadow-2xl border shrink-0"
        :style="{
          background: isDark ? '#111113' : '#18181B',
          borderColor: isDark ? '#26262A' : 'transparent',
          color: '#FFFFFF',
        }"
      >
        <div class="space-y-6 flex flex-col items-center w-full">
          <span class="font-bold text-sm">us.</span>
          <nav class="space-y-2 w-full">
            <RouterLink
              v-for="item in railItems"
              :key="'m-rail-' + item.to"
              :to="item.to"
              class="relative flex items-center justify-center py-2.5 no-underline"
              :style="{ color: route.path === item.to ? '#FFFFFF' : '#A1A1AA' }"
              @click="mobileMenuOpen = false"
            >
              <span v-if="route.path === item.to" class="absolute left-0 top-2 bottom-2 w-[3.5px] rounded-r-full bg-white" />
              <svg v-if="item.icon === 'home'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5.5v-6.5h-5V21H4a1 1 0 01-1-1v-9.5z" />
              </svg>
              <svg v-else-if="item.icon === 'inbox'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <svg v-else-if="item.icon === 'grid'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm9 0a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1V5zM4 14a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1v-5zm9 0a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1v-5z" />
              </svg>
              <svg v-else-if="item.icon === 'bank'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 10v8m4-8v8m6-8v8m4-8v8M3 18h18M12 3l9 5H3l9-5z" />
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 100 18 1.5 1.5 0 001.5-1.5c0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99a1.5 1.5 0 011.5-1.5H16a5 5 0 005-5c0-4.42-4.03-8-9-8z" />
              </svg>
            </RouterLink>
          </nav>
        </div>

        <div class="space-y-3 flex flex-col items-center">
          <button
            type="button"
            class="p-2 text-zinc-400 hover:text-white cursor-pointer"
            @click="theme.toggleQuickMode()"
          >
            <svg v-if="isDark" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.66-10h-1M4.34 12h-1m15.07 6.36l-.7-.7M6.34 6.34l-.7-.7m12.73 0l-.7.7M6.34 17.66l-.7.7M12 8a4 4 0 100 8 4 4 0 000-8z" />
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
            </svg>
          </button>
          <button type="button" class="p-2 text-zinc-400 hover:text-white cursor-pointer" @click="handleLogout">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H9m4 8H6a2 2 0 01-2-2V6a2 2 0 012-2h7" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Lavender / Matte Sub-Menu Panel (identical to desktop) -->
      <aside
        class="w-56 rounded-[22px] border py-6 px-3.5 flex flex-col justify-between shadow-2xl"
        :style="{
          background: isDark
            ? '#141416'
            : 'linear-gradient(180deg, #F3EEFA 0%, #F8F4F9 55%, #F6F4FA 100%)',
          borderColor: 'var(--border)',
        }"
      >
        <div class="space-y-6">
          <div class="px-2.5 flex items-center justify-between">
            <span class="text-lg font-bold tracking-tight" style="color: var(--text)">UniSell</span>
            <button type="button" class="text-sm px-2 py-0.5 rounded-lg" style="color: var(--muted)" @click="mobileMenuOpen = false">
              ✕
            </button>
          </div>

          <nav class="space-y-1">
            <RouterLink
              v-for="item in subMenuItems"
              :key="'m-sub-' + item.to"
              :to="item.to"
              class="flex items-center justify-between px-3 py-2.5 rounded-xl text-[13px] transition no-underline"
              :style="
                route.path === item.to
                  ? {
                      background: 'var(--surface)',
                      color: 'var(--text)',
                      fontWeight: 600,
                      boxShadow: isDark ? 'none' : '0 2px 8px rgba(30,27,75,0.06)',
                    }
                  : { color: 'var(--muted)', fontWeight: 500 }
              "
              @click="mobileMenuOpen = false"
            >
              <span class="truncate">{{ item.label }}</span>
              <span
                v-if="item.badge !== null"
                class="min-w-[22px] h-[22px] px-1.5 rounded-md flex items-center justify-center text-[11px] font-bold font-mono-code"
                :style="
                  route.path === item.to
                    ? { background: 'var(--primary)', color: 'var(--primary-fg)' }
                    : { background: 'var(--border)', color: 'var(--text)' }
                "
              >
                {{ item.badge }}
              </span>
            </RouterLink>
          </nav>

          <div class="pt-5 px-2.5 space-y-2.5 border-t" style="border-color: var(--border)">
            <div class="text-xs font-bold" style="color: var(--text)">Channels</div>
            <a href="/" class="flex items-center gap-2.5 no-underline py-1 text-[13px]" style="color: var(--muted)">
              <span>🌐</span>
              <span>Storefront Live</span>
            </a>
          </div>
        </div>

        <div class="px-2 pt-4 border-t" style="border-color: var(--border)">
          <div class="flex items-center gap-2.5">
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
              :style="{ background: 'var(--primary)', color: 'var(--primary-fg)' }"
            >
              {{ (auth.user?.name || 'A').charAt(0).toUpperCase() }}
            </div>
            <div class="min-w-0">
              <div class="text-xs font-semibold truncate">{{ auth.user?.name || 'Admin UniSell' }}</div>
              <div class="text-[11px] truncate" style="color: var(--muted)">{{ auth.user?.email || 'admin@unisell.local' }}</div>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- 1. Floating Obsidian Dark Icon Rail (desktop md+ only) -->
    <div
      class="hidden md:flex p-3 pr-2 shrink-0 sticky top-0 h-screen z-40"
      @mouseenter="openDrawer"
      @mouseleave="scheduleCloseDrawer"
    >
      <aside
        class="relative w-[68px] flex flex-col items-center justify-between py-6 rounded-[24px] shadow-xl border"
        :style="{
          background: isDark ? '#111113' : '#18181B',
          borderColor: isDark ? '#26262A' : 'transparent',
          color: '#FFFFFF',
        }"
      >
        <!-- Pin / Unpin Floating Circle Chevron -->
        <button
          type="button"
          class="absolute -right-3 top-14 w-6 h-6 rounded-full flex items-center justify-center shadow-md border text-xs cursor-pointer transition"
          :style="{
            background: drawerPinned ? 'var(--primary)' : '#FFFFFF',
            color: drawerPinned ? '#FFFFFF' : '#18181B',
            borderColor: drawerPinned ? 'var(--primary)' : '#E4E4E7',
          }"
          :title="drawerPinned ? 'Lepas pin submenu' : 'Pin submenu agar tetap terbuka'"
          @click="drawerPinned = !drawerPinned"
        >
          {{ isDrawerOpen ? '‹' : '›' }}
        </button>

        <div class="space-y-8 w-full flex flex-col items-center">
          <RouterLink to="/" class="no-underline font-bold tracking-tight text-white text-[15px]">
            us.
          </RouterLink>

          <nav class="space-y-2 w-full">
            <RouterLink
              v-for="item in railItems"
              :key="item.to"
              :to="item.to"
              class="relative flex items-center justify-center py-2.5 rounded-xl transition no-underline"
              :style="{ color: route.path === item.to ? '#FFFFFF' : '#A1A1AA' }"
              :title="item.label"
            >
              <span
                v-if="route.path === item.to"
                class="absolute left-0 top-2 bottom-2 w-[3.5px] rounded-r-full bg-white"
              />

              <svg v-if="item.icon === 'home'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5.5v-6.5h-5V21H4a1 1 0 01-1-1v-9.5z" />
              </svg>
              <svg v-else-if="item.icon === 'inbox'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <svg v-else-if="item.icon === 'grid'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm9 0a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1V5zM4 14a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1v-5zm9 0a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1v-5z" />
              </svg>
              <svg v-else-if="item.icon === 'bank'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 10v8m4-8v8m6-8v8m4-8v8M3 18h18M12 3l9 5H3l9-5z" />
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 100 18 1.5 1.5 0 001.5-1.5c0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99a1.5 1.5 0 011.5-1.5H16a5 5 0 005-5c0-4.42-4.03-8-9-8z" />
              </svg>
            </RouterLink>
          </nav>
        </div>

        <div class="space-y-3 flex flex-col items-center">
          <button
            type="button"
            class="p-2 rounded-xl text-zinc-400 hover:text-white transition cursor-pointer"
            :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            @click="theme.toggleQuickMode()"
          >
            <svg v-if="isDark" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.66-10h-1M4.34 12h-1m15.07 6.36l-.7-.7M6.34 6.34l-.7-.7m12.73 0l-.7.7M6.34 17.66l-.7.7M12 8a4 4 0 100 8 4 4 0 000-8z" />
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
            </svg>
          </button>

          <button
            type="button"
            class="p-2 rounded-xl text-zinc-400 hover:text-white transition cursor-pointer"
            title="Logout"
            @click="handleLogout"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H9m4 8H6a2 2 0 01-2-2V6a2 2 0 012-2h7" />
            </svg>
          </button>
        </div>
      </aside>
    </div>

    <!-- 2. Hover-Reveal Sub-Menu Panel (desktop md+ only) -->
    <aside
      class="hidden md:flex fixed left-[84px] top-3 bottom-3 w-60 rounded-[22px] border flex-col justify-between py-6 px-3.5 z-30 transition-all duration-200 shadow-2xl"
      :class="isDrawerOpen ? 'opacity-100 translate-x-0 pointer-events-auto' : 'opacity-0 -translate-x-4 pointer-events-none'"
      :style="{
        background: isDark
          ? '#141416'
          : 'linear-gradient(180deg, #F3EEFA 0%, #F8F4F9 55%, #F6F4FA 100%)',
        borderColor: 'var(--border)',
      }"
      @mouseenter="openDrawer"
      @mouseleave="scheduleCloseDrawer"
    >
      <div class="space-y-6">
        <div class="px-2.5 flex items-center justify-between">
          <div class="text-lg font-bold tracking-tight" style="color: var(--text)">UniSell</div>
          <button
            type="button"
            class="text-[11px] px-2 py-0.5 rounded-md border cursor-pointer"
            :style="{
              borderColor: 'var(--border)',
              color: drawerPinned ? 'var(--primary)' : 'var(--muted)',
              background: 'var(--surface)',
            }"
            @click="drawerPinned = !drawerPinned"
          >
            {{ drawerPinned ? 'Pinned' : 'Pin' }}
          </button>
        </div>

        <nav class="space-y-1">
          <RouterLink
            v-for="item in subMenuItems"
            :key="item.to"
            :to="item.to"
            class="flex items-center justify-between px-3 py-2.5 rounded-xl text-[13px] transition no-underline"
            :style="
              route.path === item.to
                ? {
                    background: 'var(--surface)',
                    color: 'var(--text)',
                    fontWeight: 600,
                    boxShadow: isDark ? 'none' : '0 2px 8px rgba(30,27,75,0.06)',
                  }
                : { color: 'var(--muted)', fontWeight: 500 }
            "
          >
            <span class="truncate">{{ item.label }}</span>
            <span
              v-if="item.badge !== null"
              class="min-w-[22px] h-[22px] px-1.5 rounded-md flex items-center justify-center text-[11px] font-bold font-mono-code"
              :style="
                route.path === item.to
                  ? { background: 'var(--primary)', color: 'var(--primary-fg)' }
                  : { background: 'var(--border)', color: 'var(--text)' }
              "
            >
              {{ item.badge }}
            </span>
          </RouterLink>
        </nav>

        <div class="pt-5 px-2.5 space-y-2.5 border-t" style="border-color: var(--border)">
          <div class="text-xs font-bold" style="color: var(--text)">Channels</div>
          <a href="/" class="flex items-center gap-2.5 no-underline py-1 text-[13px]" style="color: var(--muted)">
            <span>🌐</span>
            <span>Storefront Live</span>
          </a>
          <div class="flex items-center justify-between py-1 text-[13px]" style="color: var(--muted)">
            <span class="flex items-center gap-2.5">
              <span>⚡</span>
              <span>Manual Review</span>
            </span>
            <span v-if="reviewCount" class="font-mono-code text-xs font-semibold" style="color: var(--danger)">
              {{ reviewCount }}
            </span>
          </div>
        </div>
      </div>

      <div class="px-2 pt-4 border-t" style="border-color: var(--border)">
        <div class="flex items-center gap-2.5">
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
            :style="{ background: 'var(--primary)', color: '#FFFFFF' }"
          >
            {{ (auth.user?.name || 'A').charAt(0).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <div class="text-xs font-semibold truncate">{{ auth.user?.name || 'Admin UniSell' }}</div>
            <div class="text-[11px] truncate" style="color: var(--muted)">{{ auth.user?.email || 'admin@unisell.local' }}</div>
          </div>
        </div>
      </div>
    </aside>

    <!-- 3. Main Clean Workspace Pane (matte black doff in dark mode, pure white in light mode) -->
    <div
      class="flex-1 min-w-0 flex flex-col transition-all duration-200"
      :style="{
        background: isDark ? '#0B0B0C' : 'var(--surface)',
        marginLeft: drawerPinned ? '248px' : '0px',
      }"
    >
      <div
        v-if="!flushMain"
        class="px-8 pt-6 pb-4 flex flex-wrap items-center justify-between gap-4 border-b"
        style="border-color: var(--border)"
      >
        <div>
          <h1 class="text-2xl font-bold tracking-tight">{{ title }}</h1>
          <p v-if="subtitle" class="text-xs mt-0.5" style="color: var(--muted)">{{ subtitle }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <button
            type="button"
            class="flex items-center gap-2 px-3.5 py-2 rounded-full border text-xs cursor-pointer"
            style="background: var(--surface-2); border-color: var(--border); color: var(--muted)"
            @click="showQuickNav = true"
          >
            <span>🔍 Search</span>
            <kbd class="font-mono-code text-[10px]">⌘K</kbd>
          </button>

          <slot name="actions" />
        </div>
      </div>

      <main :class="flushMain ? 'flex-1 flex flex-col lg:flex-row min-h-screen' : 'flex-1 p-4 md:p-8 space-y-6'">
        <slot />
      </main>
    </div>

    <!-- Command Palette (Cmd+K) -->
    <div
      v-if="showQuickNav"
      class="fixed inset-0 z-50 flex items-start justify-center pt-24 px-4"
      style="background: rgba(0, 0, 0, 0.45)"
      @click.self="showQuickNav = false"
    >
      <div class="unisell-card w-full max-w-md p-3 space-y-2 shadow-2xl rounded-2xl">
        <input
          v-model="quickQuery"
          autofocus
          class="unisell-input text-sm rounded-xl"
          placeholder="Cari halaman..."
        />
        <div class="space-y-1">
          <button
            v-for="item in subMenuItems.filter((i) => i.label.toLowerCase().includes(quickQuery.toLowerCase()))"
            :key="item.to"
            type="button"
            class="w-full text-left px-3 py-2 rounded-xl text-sm flex items-center justify-between hover:opacity-90"
            style="background: var(--surface-2); color: var(--text)"
            @click="goQuick(item.to)"
          >
            <span>{{ item.label }}</span>
            <span v-if="item.badge !== null" class="text-xs font-mono-code" style="color: var(--primary)">
              {{ item.badge }}
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
