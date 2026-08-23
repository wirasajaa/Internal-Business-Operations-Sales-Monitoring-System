<script setup>
import { reactive, watch } from 'vue'
import BaseInput from '../base/BaseInput.vue'
import BaseButton from '../base/BaseButton.vue'
import BaseAlert from '../base/BaseAlert.vue'

const props = defineProps({
  initialValue: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['submit', 'cancel'])

const form = reactive({ name: '' })

watch(
  () => props.initialValue,
  (value) => {
    form.name = value?.name ?? ''
  },
  { immediate: true },
)

function handleSubmit() {
  emit('submit', { name: form.name })
}
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
    <BaseAlert v-if="errorMessage" variant="error">{{ errorMessage }}</BaseAlert>

    <BaseInput v-model="form.name" label="Nama Permission" />
    <p class="-mt-2 text-xs text-slate-500">Contoh: <code>sales.view</code>, <code>reports.export</code>.</p>

    <div class="flex justify-end gap-2">
      <BaseButton type="button" variant="secondary" @click="emit('cancel')">Batal</BaseButton>
      <BaseButton type="submit" :loading="loading">Simpan</BaseButton>
    </div>
  </form>
</template>
