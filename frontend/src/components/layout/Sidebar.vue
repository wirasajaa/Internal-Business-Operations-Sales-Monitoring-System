<script setup>
import { inject, onMounted, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import SidebarMenuItem from './SidebarMenuItem.vue'

const auth = useAuthStore()
const sidebarOpen = inject('sidebarOpen', ref(false))

const menuItems = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    menuItems.value = await auth.loadNavigationMenu()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div
    v-if="sidebarOpen"
    class="fixed inset-0 z-30 bg-black/30 md:hidden"
    @click="sidebarOpen = false"
  />

  <aside
    class="app-sidebar fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col gap-1 border-r border-line-300 bg-sidebar-bg py-6 pl-4 pr-[17px] transition-transform duration-200 md:static md:z-auto"
    :style="{ transform: sidebarOpen ? 'translateX(0)' : 'translateX(-100%)' }"
  >
    <div class="flex items-center gap-4 px-4 pb-6">
      <div class="flex h-10 w-[22px] shrink-0 items-center justify-center rounded bg-black">
        <span class="text-lg font-bold text-white">E</span>
      </div>
      <div>
        <h1 class="text-xl font-black leading-tight text-black">ERP Internal</h1>
        <p class="text-[11px] font-semibold tracking-wide text-ink-600">Enterprise Suite</p>
      </div>
    </div>

    <nav class="flex flex-1 flex-col gap-1 overflow-auto" @click="(e) => e.target.closest('a') && (sidebarOpen = false)">
      <p v-if="loading" class="px-4 py-2 text-sm text-ink-600">Memuat menu...</p>
      <SidebarMenuItem v-for="item in menuItems" :key="item.id" :item="item" />
    </nav>
  </aside>
</template>

<style scoped>
/* Desktop always shows the sidebar in normal flow, regardless of the mobile drawer's inline transform. */
@media (min-width: 768px) {
  .app-sidebar {
    transform: none !important;
  }
}
</style>
