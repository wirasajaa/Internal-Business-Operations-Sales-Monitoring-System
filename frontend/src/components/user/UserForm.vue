<script setup>
import { reactive, watch } from 'vue'
import BaseInput from '../base/BaseInput.vue'
import BaseButton from '../base/BaseButton.vue'
import BaseAlert from '../base/BaseAlert.vue'

// Edit-only: identity/credentials come from bpms.users (see
// dev-doc/user-role-permission-management/requirement-conflicts/create-user-disabled-2026-08-24.md).
// Only the local display name and role assignment can be changed here.
const props = defineProps({
  initialValue: { type: Object, default: null },
  roleOptions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['submit', 'cancel'])

const form = reactive({ name: '', role_id: '' })

watch(
  () => props.initialValue,
  (value) => {
    form.name = value?.name ?? ''
    form.role_id = value?.roles?.[0]?.id ?? ''
  },
  { immediate: true },
)

function handleSubmit() {
  emit('submit', {
    name: form.name,
    role_id: form.role_id,
  })
}
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
    <BaseAlert v-if="errorMessage" variant="error">{{ errorMessage }}</BaseAlert>

    <BaseInput v-model="form.name" label="Nama" />

    <div>
      <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
      <select
        v-model="form.role_id"
        required
        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
      >
        <option value="" disabled>— Pilih role —</option>
        <option v-for="role in roleOptions" :key="role.id" :value="role.id">{{ role.name }}</option>
      </select>
    </div>

    <div class="flex justify-end gap-2">
      <BaseButton type="button" variant="secondary" @click="emit('cancel')">Batal</BaseButton>
      <BaseButton type="submit" :loading="loading">Simpan</BaseButton>
    </div>
  </form>
</template>
