<script setup>
import { onMounted, ref } from 'vue'
import api from '../services/api'
import BaseCard from '../components/base/BaseCard.vue'
import BaseAlert from '../components/base/BaseAlert.vue'

const loading = ref(true)
const errorMessage = ref('')
const summaryMessage = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/dashboard/summary')
    summaryMessage.value = data.message
  } catch {
    errorMessage.value = 'Gagal memuat data dashboard.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
      <h1 class="text-3xl font-bold tracking-tight text-ink-900 sm:text-5xl">Dashboard</h1>
      <p class="text-base text-ink-600">Ringkasan aktivitas dan metrik utama ERP Internal.</p>
    </div>

    <BaseCard v-if="loading">
      <p class="text-sm text-slate-500">Memuat...</p>
    </BaseCard>

    <BaseAlert v-else-if="errorMessage" variant="error">{{ errorMessage }}</BaseAlert>

    <BaseCard v-else>
      <p class="text-sm text-slate-700">{{ summaryMessage }}</p>
      <p class="mt-2 text-sm text-slate-400">
        Modul lain (Sales, dsb.) belum tersedia pada slice ini.
      </p>
    </BaseCard>
  </div>
</template>
