<script setup>
import { onMounted, ref } from 'vue'
import { fetchRoles, fetchAllPermissions, createRole, updateRole, deleteRole } from '../services/roles'
import BaseCard from '../components/base/BaseCard.vue'
import BaseButton from '../components/base/BaseButton.vue'
import BaseAlert from '../components/base/BaseAlert.vue'
import RoleForm from '../components/role/RoleForm.vue'

const roles = ref([])
const permissions = ref([])
const loading = ref(true)
const loadError = ref('')
const showForm = ref(false)
const editingRole = ref(null)
const formError = ref('')
const formLoading = ref(false)
const actionError = ref('')

async function loadData() {
  loading.value = true
  loadError.value = ''
  try {
    const [roleData, permissionData] = await Promise.all([fetchRoles(), fetchAllPermissions()])
    roles.value = roleData
    permissions.value = permissionData
  } catch {
    loadError.value = 'Gagal memuat data role.'
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

function openCreateForm() {
  editingRole.value = null
  formError.value = ''
  showForm.value = true
}

function openEditForm(role) {
  editingRole.value = role
  formError.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingRole.value = null
}

async function handleSubmit(payload) {
  formLoading.value = true
  formError.value = ''
  try {
    if (editingRole.value) {
      await updateRole(editingRole.value.id, payload)
    } else {
      await createRole(payload)
    }
    closeForm()
    await loadData()
  } catch (error) {
    const errors = error.response?.data?.errors
    formError.value = errors
      ? Object.values(errors).flat().join(' ')
      : error.response?.data?.message || 'Gagal menyimpan role.'
  } finally {
    formLoading.value = false
  }
}

async function handleDelete(role) {
  actionError.value = ''
  try {
    await deleteRole(role.id)
    await loadData()
  } catch (error) {
    actionError.value = error.response?.data?.message || 'Gagal menghapus role.'
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-semibold text-slate-900">Role Management</h1>
      <BaseButton @click="openCreateForm">Tambah Role</BaseButton>
    </div>

    <BaseAlert v-if="loadError" variant="error">{{ loadError }}</BaseAlert>
    <BaseAlert v-if="actionError" variant="error">{{ actionError }}</BaseAlert>

    <BaseCard v-if="showForm">
      <h2 class="mb-4 text-base font-semibold text-slate-900">{{ editingRole ? 'Edit Role' : 'Tambah Role' }}</h2>
      <RoleForm
        :initial-value="editingRole"
        :permission-options="permissions"
        :loading="formLoading"
        :error-message="formError"
        @submit="handleSubmit"
        @cancel="closeForm"
      />
    </BaseCard>

    <BaseCard>
      <p v-if="loading" class="text-sm text-slate-500">Memuat...</p>
      <p v-else-if="!roles.length" class="text-sm text-slate-500">Belum ada role.</p>
      <div v-else class="flex flex-col gap-1">
        <div
          v-for="role in roles"
          :key="role.id"
          class="flex items-center justify-between rounded-md border border-transparent px-3 py-2 hover:border-line-200 hover:bg-slate-50"
        >
          <div class="flex flex-col">
            <span class="text-sm font-medium text-slate-900">{{ role.name }}</span>
            <span class="text-xs text-slate-400">
              {{ role.permissions?.length ? role.permissions.map((p) => p.name).join(', ') : 'Tanpa permission' }}
            </span>
          </div>
          <div class="flex gap-2">
            <button type="button" class="text-sm text-brand-600 hover:underline" @click="openEditForm(role)">
              Edit
            </button>
            <button type="button" class="text-sm text-red-600 hover:underline" @click="handleDelete(role)">
              Hapus
            </button>
          </div>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
