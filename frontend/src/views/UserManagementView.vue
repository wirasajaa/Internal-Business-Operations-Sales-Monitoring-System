<script setup>
import { onMounted, ref } from 'vue'
import { fetchUsers, updateUser, activateUser, deactivateUser } from '../services/users'
import { fetchRoles } from '../services/roles'
import BaseCard from '../components/base/BaseCard.vue'
import BaseAlert from '../components/base/BaseAlert.vue'
import UserForm from '../components/user/UserForm.vue'

// User creation is no longer available here — identity comes from bpms.users, a
// local record is auto-provisioned on that user's first successful login. See
// dev-doc/user-role-permission-management/requirement-conflicts/create-user-disabled-2026-08-24.md.
const users = ref([])
const roles = ref([])
const loading = ref(true)
const loadError = ref('')
const showForm = ref(false)
const editingUser = ref(null)
const formError = ref('')
const formLoading = ref(false)
const actionError = ref('')

async function loadData() {
  loading.value = true
  loadError.value = ''
  try {
    const [userData, roleData] = await Promise.all([fetchUsers(), fetchRoles()])
    users.value = userData
    roles.value = roleData
  } catch {
    loadError.value = 'Gagal memuat data user.'
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

function openEditForm(user) {
  editingUser.value = user
  formError.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingUser.value = null
}

async function handleSubmit(payload) {
  formLoading.value = true
  formError.value = ''
  try {
    await updateUser(editingUser.value.id, payload)
    closeForm()
    await loadData()
  } catch (error) {
    const errors = error.response?.data?.errors
    formError.value = errors
      ? Object.values(errors).flat().join(' ')
      : error.response?.data?.message || 'Gagal menyimpan user.'
  } finally {
    formLoading.value = false
  }
}

async function handleToggleActive(user) {
  actionError.value = ''
  try {
    if (user.is_active) {
      await deactivateUser(user.id)
    } else {
      await activateUser(user.id)
    }
    await loadData()
  } catch (error) {
    actionError.value = error.response?.data?.message || 'Gagal mengubah status user.'
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-semibold text-slate-900">User Management</h1>
    </div>
    <p class="text-xs text-slate-500">
      User baru otomatis terdaftar saat pertama kali login. Assign role di sini setelah user tersebut login.
    </p>

    <BaseAlert v-if="loadError" variant="error">{{ loadError }}</BaseAlert>
    <BaseAlert v-if="actionError" variant="error">{{ actionError }}</BaseAlert>

    <BaseCard v-if="showForm">
      <h2 class="mb-4 text-base font-semibold text-slate-900">Edit User</h2>
      <UserForm
        :initial-value="editingUser"
        :role-options="roles"
        :loading="formLoading"
        :error-message="formError"
        @submit="handleSubmit"
        @cancel="closeForm"
      />
    </BaseCard>

    <BaseCard>
      <p v-if="loading" class="text-sm text-slate-500">Memuat...</p>
      <p v-else-if="!users.length" class="text-sm text-slate-500">Belum ada user.</p>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead>
            <tr class="border-b border-line-200 text-xs font-semibold text-slate-500">
              <th class="py-2 pr-4">Nama</th>
              <th class="py-2 pr-4">Username</th>
              <th class="py-2 pr-4">Role</th>
              <th class="py-2 pr-4">Status</th>
              <th class="py-2 pr-4">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-200">
            <tr v-for="user in users" :key="user.id">
              <td class="py-2 pr-4 font-medium text-slate-900">{{ user.name }}</td>
              <td class="py-2 pr-4 text-slate-600">{{ user.username }}</td>
              <td class="py-2 pr-4 text-slate-600">{{ user.roles?.[0]?.name ?? '—' }}</td>
              <td class="py-2 pr-4">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="user.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                >
                  {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="py-2 pr-4">
                <div class="flex gap-3">
                  <button type="button" class="text-sm text-brand-600 hover:underline" @click="openEditForm(user)">
                    Edit
                  </button>
                  <button
                    type="button"
                    class="text-sm hover:underline"
                    :class="user.is_active ? 'text-red-600' : 'text-emerald-600'"
                    @click="handleToggleActive(user)"
                  >
                    {{ user.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>
  </div>
</template>
