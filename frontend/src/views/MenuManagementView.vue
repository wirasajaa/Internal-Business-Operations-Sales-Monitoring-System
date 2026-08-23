<script setup>
import { computed, onMounted, ref } from 'vue'
import { fetchMenus, fetchAvailablePermissions, createMenu, updateMenu, deleteMenu } from '../services/menus'
import BaseCard from '../components/base/BaseCard.vue'
import BaseButton from '../components/base/BaseButton.vue'
import BaseAlert from '../components/base/BaseAlert.vue'
import MenuTreeItem from '../components/menu/MenuTreeItem.vue'
import MenuForm from '../components/menu/MenuForm.vue'

const menus = ref([])
const permissions = ref([])
const loading = ref(true)
const loadError = ref('')
const showForm = ref(false)
const editingMenu = ref(null)
const formError = ref('')
const formLoading = ref(false)

function flattenForParentOptions(nodes, depth = 1, excludeId = null, result = []) {
  for (const node of nodes) {
    if (node.id !== excludeId) {
      result.push({ id: node.id, label: node.label, depth })
      if (depth < 2 && node.children?.length) {
        flattenForParentOptions(node.children, depth + 1, excludeId, result)
      }
    }
  }
  return result
}

const parentOptions = computed(() => flattenForParentOptions(menus.value, 1, editingMenu.value?.id ?? null))

async function loadData() {
  loading.value = true
  loadError.value = ''
  try {
    const [menuData, permissionData] = await Promise.all([fetchMenus(), fetchAvailablePermissions()])
    menus.value = menuData
    permissions.value = permissionData
  } catch {
    loadError.value = 'Gagal memuat data menu.'
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

function openCreateForm() {
  editingMenu.value = null
  formError.value = ''
  showForm.value = true
}

function openEditForm(menu) {
  editingMenu.value = menu
  formError.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingMenu.value = null
}

async function handleSubmit(payload) {
  formLoading.value = true
  formError.value = ''
  try {
    if (editingMenu.value) {
      await updateMenu(editingMenu.value.id, payload)
    } else {
      await createMenu(payload)
    }
    closeForm()
    await loadData()
  } catch (error) {
    const errors = error.response?.data?.errors
    formError.value = errors ? Object.values(errors).flat().join(' ') : 'Gagal menyimpan menu.'
  } finally {
    formLoading.value = false
  }
}

async function handleDelete(menu) {
  loadError.value = ''
  try {
    await deleteMenu(menu.id)
    await loadData()
  } catch (error) {
    loadError.value = error.response?.data?.message || 'Gagal menghapus menu.'
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-semibold text-slate-900">Menu Management</h1>
      <BaseButton @click="openCreateForm">Tambah Menu</BaseButton>
    </div>

    <BaseAlert v-if="loadError" variant="error">{{ loadError }}</BaseAlert>

    <BaseCard v-if="showForm">
      <h2 class="mb-4 text-base font-semibold text-slate-900">
        {{ editingMenu ? 'Edit Menu' : 'Tambah Menu' }}
      </h2>
      <MenuForm
        :initial-value="editingMenu"
        :parent-options="parentOptions"
        :permission-options="permissions"
        :loading="formLoading"
        :error-message="formError"
        @submit="handleSubmit"
        @cancel="closeForm"
      />
    </BaseCard>

    <BaseCard>
      <p v-if="loading" class="text-sm text-slate-500">Memuat...</p>
      <p v-else-if="!menus.length" class="text-sm text-slate-500">Belum ada menu.</p>
      <div v-else class="flex flex-col gap-1">
        <MenuTreeItem
          v-for="item in menus"
          :key="item.id"
          :item="item"
          @edit="openEditForm"
          @delete="handleDelete"
        />
      </div>
    </BaseCard>
  </div>
</template>
