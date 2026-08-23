<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import chevronIcon from '../../assets/icons/chevron.svg'

const props = defineProps({
  item: { type: Object, required: true },
  depth: { type: Number, default: 0 },
})

const route = useRoute()
const hasChildren = props.item.children?.length > 0
const containsActiveChild = (item) =>
  item.path === route.path || (item.children ?? []).some(containsActiveChild)
const expanded = ref(hasChildren && containsActiveChild(props.item))
</script>

<template>
  <div>
    <button
      v-if="hasChildren"
      type="button"
      class="flex w-full items-center justify-between rounded-lg px-4 py-2 text-xs font-medium text-ink-600 transition-colors hover:bg-black/5"
      :style="depth > 0 ? { paddingLeft: `${1 + depth}rem` } : null"
      @click="expanded = !expanded"
    >
      <span>{{ item.label }}</span>
      <img :src="chevronIcon" alt="" class="h-2 w-[5px] transition-transform" :class="{ 'rotate-90': expanded }" />
    </button>

    <router-link
      v-else
      :to="item.path ?? '#'"
      class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-medium text-ink-600 transition-colors hover:bg-black/5"
      :style="depth > 0 ? { paddingLeft: `${1 + depth}rem` } : null"
      active-class="bg-sidebar-active! text-white!"
      exact-active-class="bg-sidebar-active! text-white!"
    >
      {{ item.label }}
    </router-link>

    <div v-if="hasChildren && expanded" class="mt-1 flex flex-col gap-1 border-l border-line-300 pl-2">
      <SidebarMenuItem v-for="child in item.children" :key="child.id" :item="child" :depth="depth + 1" />
    </div>
  </div>
</template>
