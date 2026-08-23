<script setup>
import { reactive, watch } from 'vue'
import BaseInput from '../base/BaseInput.vue'
import BaseButton from '../base/BaseButton.vue'
import BaseAlert from '../base/BaseAlert.vue'

const props = defineProps({
  initialValue: { type: Object, default: null },
  permissionOptions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['submit', 'cancel'])

const form = reactive({ name: '', permission_ids: [] })

watch(
  () => props.initialValue,
  (value) => {
    form.name = value?.name ?? ''
    form.permission_ids = (value?.permissions ?? []).map((p) => p.id)
  },
  { immediate: true },
)

function handleSubmit() {
  emit('submit', { name: form.name, permission_ids: form.permission_ids })
}
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
    <BaseAlert v-if="errorMessage" variant="error">{{ errorMessage }}</BaseAlert>

    <BaseInput v-model="form.name" label="Nama Role" />

    <div>
      <label class="mb-1.5 block text-sm font-medium text-slate-700">Permissions</label>
      <div v-if="!permissionOptions.length" class="text-sm text-slate-500">Belum ada permission tersedia.</div>
      <div v-else class="grid max-h-64 grid-cols-1 gap-1.5 overflow-y-auto rounded-md border border-line-300 p-3 sm:grid-cols-2">
        <label
          v-for="permission in permissionOptions"
          :key="permission.id"
          class="flex items-center gap-2 text-sm text-slate-700"
        >
          <input v-model="form.permission_ids" type="checkbox" :value="permission.id" class="rounded border-slate-300" />
          {{ permission.name }}
        </label>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <BaseButton type="button" variant="secondary" @click="emit('cancel')">Batal</BaseButton>
      <BaseButton type="submit" :loading="loading">Simpan</BaseButton>
    </div>
  </form>
</template>
