<script setup>
import { reactive, watch } from 'vue'
import BaseInput from '../base/BaseInput.vue'
import BaseButton from '../base/BaseButton.vue'
import BaseAlert from '../base/BaseAlert.vue'

const props = defineProps({
  initialValue: { type: Object, default: null },
  parentOptions: { type: Array, default: () => [] },
  permissionOptions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['submit', 'cancel'])

const form = reactive({
  label: '',
  path: '',
  icon: '',
  parent_id: '',
  permission_name: '',
  order: 0,
  is_active: true,
})

watch(
  () => props.initialValue,
  (value) => {
    form.label = value?.label ?? ''
    form.path = value?.path ?? ''
    form.icon = value?.icon ?? ''
    form.parent_id = value?.parent_id ?? ''
    form.permission_name = value?.permission_name ?? ''
    form.order = value?.order ?? 0
    form.is_active = value?.is_active ?? true
  },
  { immediate: true },
)

function handleSubmit() {
  emit('submit', {
    label: form.label,
    path: form.path || null,
    icon: form.icon || null,
    parent_id: form.parent_id || null,
    permission_name: form.permission_name || null,
    order: Number(form.order) || 0,
    is_active: form.is_active,
  })
}
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
    <BaseAlert v-if="errorMessage" variant="error">{{ errorMessage }}</BaseAlert>

    <BaseInput v-model="form.label" label="Label" />
    <BaseInput v-model="form.path" label="Path (opsional)" />
    <BaseInput v-model="form.icon" label="Icon (opsional)" />

    <div>
      <label class="mb-1 block text-sm font-medium text-slate-700">Parent (opsional)</label>
      <select
        v-model="form.parent_id"
        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
      >
        <option value="">— Tanpa parent (level 1) —</option>
        <option v-for="option in parentOptions" :key="option.id" :value="option.id">
          {{ '—'.repeat(option.depth - 1) }} {{ option.label }}
        </option>
      </select>
    </div>

    <div>
      <label class="mb-1 block text-sm font-medium text-slate-700">Permission (opsional)</label>
      <select
        v-model="form.permission_name"
        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
      >
        <option value="">— Tampil untuk semua user login —</option>
        <option v-for="name in permissionOptions" :key="name" :value="name">{{ name }}</option>
      </select>
    </div>

    <BaseInput v-model="form.order" label="Urutan" type="number" />

    <label class="flex items-center gap-2 text-sm text-slate-700">
      <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
      Aktif
    </label>

    <div class="flex justify-end gap-2">
      <BaseButton type="button" variant="secondary" @click="emit('cancel')">Batal</BaseButton>
      <BaseButton type="submit" :loading="loading">Simpan</BaseButton>
    </div>
  </form>
</template>
