<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import caretDownIcon from '../../assets/icons/caret-down.svg'

const auth = useAuthStore()
const router = useRouter()
const sidebarOpen = inject('sidebarOpen', ref(false))

const open = ref(false)
const menuRef = ref(null)

function handleClickOutside(event) {
  if (menuRef.value && !menuRef.value.contains(event.target)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', handleClickOutside))

const initials = computed(() => {
  const name = auth.user?.name ?? ''
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
})

const roleLabel = computed(() => auth.roles?.[0] ?? '')

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="flex h-16 items-center justify-between border-b border-line-300 bg-white px-4 sm:px-6">
    <button
      type="button"
      class="rounded-lg p-2 text-ink-600 hover:bg-slate-50 md:hidden"
      aria-label="Buka menu navigasi"
      @click="sidebarOpen = !sidebarOpen"
    >
      <svg width="20" height="16" viewBox="0 0 20 16" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 1H20M0 8H20M0 15H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
      </svg>
    </button>

    <div ref="menuRef" class="relative ml-auto">
      <button
        type="button"
        class="flex items-center gap-2 rounded-lg p-1 transition-colors hover:bg-slate-50"
        @click="open = !open"
      >
        <span
          class="flex h-8 w-8 items-center justify-center rounded-full border border-line-300 bg-brand-50 text-xs font-bold text-brand-700"
        >
          {{ initials }}
        </span>
        <span class="hidden pr-1 text-right sm:block">
          <span class="block text-xs font-bold text-ink-900">{{ auth.user?.name }}</span>
          <span class="block text-[11px] font-semibold tracking-wide text-ink-600">{{ roleLabel }}</span>
        </span>
        <img :src="caretDownIcon" alt="" class="h-[3.75px] w-[7.5px]" />
      </button>

      <div
        v-if="open"
        class="absolute right-0 top-full mt-2 w-40 rounded-lg border border-line-200 bg-white py-1 shadow-lg"
      >
        <button
          type="button"
          class="block w-full px-4 py-2 text-left text-sm text-ink-600 hover:bg-slate-50"
          @click="handleLogout"
        >
          Logout
        </button>
      </div>
    </div>
  </header>
</template>
