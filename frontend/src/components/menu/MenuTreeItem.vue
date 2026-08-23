<script setup>
defineProps({
  item: { type: Object, required: true },
  depth: { type: Number, default: 0 },
})

defineEmits(['edit', 'delete'])
</script>

<template>
  <div>
    <div
      class="flex items-center justify-between rounded-md border border-transparent px-3 py-2 hover:border-slate-200 hover:bg-slate-50"
      :style="{ marginLeft: `${depth * 1.25}rem` }"
    >
      <div class="flex flex-col">
        <span class="text-sm font-medium text-slate-900">{{ item.label }}</span>
        <span class="text-xs text-slate-400">
          {{ item.path || '(tanpa link)' }}
          <template v-if="item.permission_name"> · butuh permission: {{ item.permission_name }}</template>
          <template v-if="!item.is_active"> · nonaktif</template>
        </span>
      </div>
      <div class="flex gap-2">
        <button type="button" class="text-sm text-brand-600 hover:underline" @click="$emit('edit', item)">
          Edit
        </button>
        <button type="button" class="text-sm text-red-600 hover:underline" @click="$emit('delete', item)">
          Hapus
        </button>
      </div>
    </div>

    <div v-if="item.children?.length" class="mt-1 flex flex-col gap-1">
      <MenuTreeItem
        v-for="child in item.children"
        :key="child.id"
        :item="child"
        :depth="depth + 1"
        @edit="$emit('edit', $event)"
        @delete="$emit('delete', $event)"
      />
    </div>
  </div>
</template>
