<script setup>
import { onMounted, ref } from 'vue'
import { fetchPermissions, createPermission, updatePermission, deletePermission } from '../services/permissions'
import BaseCard from '../components/base/BaseCard.vue'
import BaseButton from '../components/base/BaseButton.vue'
import BaseAlert from '../components/base/BaseAlert.vue'
import PermissionForm from '../components/permission/PermissionForm.vue'

const permissions = ref([])
const loading = ref(true)
const loadError = ref('')
const showForm = ref(false)
const editingPermission = ref(null)
const formError = ref('')
const formLoading = ref(false)
const actionError = ref('')

async function loadData() {
  loading.value = true
  loadError.value = ''
  try {
    permissions.value = await fetchPermissions()
  } catch {
    loadError.value = 'Gagal memuat data permission.'
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

function openCreateForm() {
  editingPermission.value = null
  formError.value = ''
  showForm.value = true
}

function openEditForm(permission) {
  editingPermission.value = permission
  formError.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingPermission.value = null
}

async function handleSubmit(payload) {
  formLoading.value = true
  formError.value = ''
  try {
    if (editingPermission.value) {
      await updatePermission(editingPermission.value.id, payload)
    } else {
      await createPermission(payload)
    }
    closeForm()
    await loadData()
  } catch (error) {
    const errors = error.response?.data?.errors
    formError.value = errors
      ? Object.values(errors).flat().join(' ')
      : error.response?.data?.message || 'Gagal menyimpan permission.'
  } finally {
    formLoading.value = false
  }
}

async function handleDelete(permission) {
  actionError.value = ''
  try {
    await deletePermission(permission.id)
    await loadData()
  } catch (error) {
    actionError.value = error.response?.data?.message || 'Gagal menghapus permission.'
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-semibold text-slate-900">Permission Management</h1>
      <BaseButton @click="openCreateForm">Tambah Permission</BaseButton>
    </div>

    <BaseAlert v-if="loadError" variant="error">{{ loadError }}</BaseAlert>
    <BaseAlert v-if="actionError" variant="error">{{ actionError }}</BaseAlert>

    <BaseCard v-if="showForm">
      <h2 class="mb-4 text-base font-semibold text-slate-900">
        {{ editingPermission ? 'Edit Permission' : 'Tambah Permission' }}
      </h2>
      <PermissionForm
        :initial-value="editingPermission"
        :loading="formLoading"
        :error-message="formError"
        @submit="handleSubmit"
        @cancel="closeForm"
      />
    </BaseCard>

    <BaseCard>
      <p v-if="loading" class="text-sm text-slate-500">Memuat...</p>
      <p v-else-if="!permissions.length" class="text-sm text-slate-500">Belum ada permission.</p>
      <div v-else class="flex flex-col gap-1">
        <div
          v-for="permission in permissions"
          :key="permission.id"
          class="flex items-center justify-between rounded-md border border-transparent px-3 py-2 hover:border-line-200 hover:bg-slate-50"
        >
          <span class="text-sm font-medium text-slate-900">{{ permission.name }}</span>
          <div class="flex gap-2">
            <button type="button" class="text-sm text-brand-600 hover:underline" @click="openEditForm(permission)">
              Edit
            </button>
            <button type="button" class="text-sm text-red-600 hover:underline" @click="handleDelete(permission)">
              Hapus
            </button>
          </div>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
