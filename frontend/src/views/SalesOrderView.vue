<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import BaseButton from '../components/base/BaseButton.vue'
import BaseAlert from '../components/base/BaseAlert.vue'
import { fetchSalesOrders, fetchOrderStatuses, updateOrderStatus } from '../services/salesOrders'

/*
 * The "Sales" column is wired to real data from sales.get_sales_order_for_update_status.
 * Department status columns (finance..leader) are pre-filled from the same response —
 * SalesOrderController@index already corrects those 6 fields to the real status id
 * (the source function itself aliases the wrong column; worked around server-side), so
 * they pre-select correctly here.
 */
const orders = ref([])
const loading = ref(true)
const loadError = ref('')
const statusUpdateError = ref('')
const statusUpdateSuccess = ref('')

function mapOrder(row) {
  return {
    id: row.id,
    code: row.id,
    createdBy: row.created_by,
    jenisOrder: row.jenis_order,
    tglSo: row.tgl_so ? formatDate(new Date(row.tgl_so)) : '-',
    tglAd: row.tgl_ad ? formatDate(new Date(row.tgl_ad)) : '-',
    status: reactive({
      finance: row.finance ?? '',
      ppic: row.ppic ?? '',
      design: row.design ?? '',
      purchasing: row.purchasing ?? '',
      warehouse: row.warehouse ?? '',
      leader: row.leader_produksi ?? '',
    }),
    saving: reactive({ finance: false, ppic: false, design: false, purchasing: false, warehouse: false, leader: false }),
  }
}

/**
 * Called when a per-row department status select changes. Persists via
 * sales.set_new_sales_order_for_update_status (fixed by the founder 2026-08-25 — both the
 * first-time-insert and update-existing-row paths verified live). Optimistically applies
 * the new value; shows a brief success message on success, reverts + shows an error on
 * failure. `order.saving[uiKey]` drives the loading spinner on the select while in flight.
 */
async function handleStatusChange(order, uiKey, newStatusId) {
  const previous = order.status[uiKey]
  order.status[uiKey] = newStatusId
  order.saving[uiKey] = true
  statusUpdateError.value = ''
  statusUpdateSuccess.value = ''
  try {
    await updateOrderStatus(order.id, departmentTypeMap[uiKey], newStatusId)
    statusUpdateSuccess.value = 'Status berhasil diperbarui.'
    setTimeout(() => {
      statusUpdateSuccess.value = ''
    }, 3000)
  } catch (error) {
    order.status[uiKey] = previous
    statusUpdateError.value = error.response?.data?.message || 'Gagal menyimpan status sales order.'
  } finally {
    order.saving[uiKey] = false
  }
}

async function loadOrders(filters = {}) {
  loading.value = true
  loadError.value = ''
  try {
    const data = await fetchSalesOrders(filters)
    orders.value = data.map(mapOrder)
  } catch (error) {
    loadError.value = error.response?.data?.message || 'Gagal memuat data sales order.'
  } finally {
    loading.value = false
  }
}

const departments = [
  { key: 'finance', label: 'Finance', width: 'w-40' },
  { key: 'ppic', label: 'PPIC', width: 'w-40' },
  { key: 'design', label: 'Design', width: 'w-40' },
  { key: 'purchasing', label: 'Purchasing', width: 'w-52' },
  { key: 'warehouse', label: 'Warehouse', width: 'w-44' },
  { key: 'leader', label: 'Leader Produksi', width: 'w-48' },
]

/* UI uses the short key "leader"; the real master data's type column is "leader_produksi". */
const departmentTypeMap = {
  finance: 'finance',
  ppic: 'ppic',
  design: 'design',
  purchasing: 'purchasing',
  warehouse: 'warehouse',
  leader: 'leader_produksi',
}

/*
 * Department status options, sourced from sales.master_sales_order_status via
 * sales.get_master_sales_order_status (grouped server-side by type — the function's own
 * `type` filter parameter doesn't work, see SalesOrderController@statuses). Each dept's
 * list is an array of {id, name, sort_order}; every select (filter + per-row) binds its
 * value to `opt.id`, matching both the filter contract and set_new_sales_order_for_
 * update_status's expected `status_id`.
 */
const statusOptions = reactive({ finance: [], ppic: [], design: [], purchasing: [], warehouse: [], leader: [] })

async function loadStatusOptions() {
  try {
    const grouped = await fetchOrderStatuses()
    for (const [uiKey, dbType] of Object.entries(departmentTypeMap)) {
      statusOptions[uiKey] = grouped[dbType] ?? []
    }
  } catch {
    // Non-fatal: dropdowns simply stay empty if the master status source is unavailable.
  }
}

/*
 * Filter card state. Jenis Order stays decorative — the source SQL function still
 * hardcodes its output to null (no real value exists anywhere to filter on), confirmed
 * live. `find`, Tgl SO/AD range, and the 6 department filters are all real, server-side
 * filters now (sales.get_sales_order_for_update_status confirmed fixed 2026-08-25) — see
 * SalesOrderController@index, which just passes these straight through.
 * Department filter values are 'semua' | 'kosong' | a real sales.master_sales_order_status
 * id, matching the SQL function's own contract exactly.
 */
const jenisOrderFilter = ref('')
const departmentFilters = reactive({
  finance: 'semua', ppic: 'semua', design: 'semua', purchasing: 'semua', warehouse: 'semua', leader: 'semua',
})
const filterApplied = ref(false)

function toIsoDate(date) {
  if (!date) return ''
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function currentFilterPayload() {
  const payload = {
    find: searchQuery.value.trim(),
    is_date_so: soRange.state.enabled,
    start_so: toIsoDate(soRange.state.start),
    end_so: toIsoDate(soRange.state.end),
    is_date_ad: adRange.state.enabled,
    start_ad: toIsoDate(adRange.state.start),
    end_ad: toIsoDate(adRange.state.end),
  }
  for (const [uiKey, dbType] of Object.entries(departmentTypeMap)) {
    payload[dbType] = departmentFilters[uiKey]
  }
  return payload
}

/*
 * Persists the last-applied filter card state in sessionStorage so it survives a page
 * refresh (per-tab, cleared when the tab closes — same storage mechanism already used
 * elsewhere in this app for the navigation menu cache). Only saved when a filter is
 * actually applied (Terapkan Filter) or reset, not on every keystroke/click.
 */
const FILTER_STORAGE_KEY = 'salesOrders.filters'

function saveFilterState() {
  try {
    sessionStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify({
      search: searchQuery.value,
      jenisOrder: jenisOrderFilter.value,
      departments: { ...departmentFilters },
      so: { enabled: soRange.state.enabled, start: toIsoDate(soRange.state.start), end: toIsoDate(soRange.state.end) },
      ad: { enabled: adRange.state.enabled, start: toIsoDate(adRange.state.start), end: toIsoDate(adRange.state.end) },
    }))
  } catch {
    // sessionStorage unavailable (e.g. private browsing) — filters just won't persist.
  }
}

function restoreFilterState() {
  try {
    const raw = sessionStorage.getItem(FILTER_STORAGE_KEY)
    if (!raw) return false
    const saved = JSON.parse(raw)

    searchQuery.value = saved.search ?? ''
    jenisOrderFilter.value = saved.jenisOrder ?? ''
    Object.assign(departmentFilters, saved.departments ?? {})
    soRange.state.enabled = saved.so?.enabled ?? false
    soRange.state.start = saved.so?.start ? new Date(saved.so.start) : null
    soRange.state.end = saved.so?.end ? new Date(saved.so.end) : null
    adRange.state.enabled = saved.ad?.enabled ?? false
    adRange.state.start = saved.ad?.start ? new Date(saved.ad.start) : null
    adRange.state.end = saved.ad?.end ? new Date(saved.ad.end) : null
    return true
  } catch {
    return false
  }
}

function clearFilterState() {
  try {
    sessionStorage.removeItem(FILTER_STORAGE_KEY)
  } catch {
    // Non-fatal.
  }
}

async function applyFilter() {
  await loadOrders(currentFilterPayload())
  saveFilterState()
  filterApplied.value = true
  setTimeout(() => {
    filterApplied.value = false
  }, 1000)
}

/* Search — the only functionally filtering control, matching the mockup. */
const searchQuery = ref('')
const filteredOrders = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase()
  if (!keyword) return orders.value
  return orders.value.filter((order) =>
    `${order.code} ${order.createdBy ?? ''} ${order.jenisOrder ?? ''} ${order.tglSo} ${order.tglAd}`
      .toLowerCase()
      .includes(keyword),
  )
})

const limitOptions = ['10', '25', '50', '100']
const limitSelect = ref('10')

/*
 * Reset clears the filter card (department filters back to 'semua'), unchecks both date
 * ranges, and the search box, then refetches the fully unfiltered list.
 */
function resetAll() {
  jenisOrderFilter.value = ''
  for (const key of Object.keys(departmentFilters)) departmentFilters[key] = 'semua'
  searchQuery.value = ''
  soRange.reset()
  adRange.reset()
  clearFilterState()
  loadOrders()
}

/* Minimal date-range picker, ported from the mockup's vanilla-JS calendar. */
const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const weekdayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

function sameDay(a, b) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
}

function formatDate(date) {
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(date)
}

function createDateRange() {
  const state = reactive({
    enabled: false,
    open: false,
    viewYear: 2026,
    viewMonth: 4,
    start: null,
    end: null,
    tempStart: null,
    tempEnd: null,
  })

  const monthLabel = computed(() => `${monthNames[state.viewMonth]} ${state.viewYear}`)
  const rangeText = computed(() =>
    state.start && state.end ? `${formatDate(state.start)} — ${formatDate(state.end)}` : 'Pilih rentang tanggal',
  )

  const calendarDays = computed(() => {
    const first = new Date(state.viewYear, state.viewMonth, 1)
    const offset = first.getDay()
    const totalDays = new Date(state.viewYear, state.viewMonth + 1, 0).getDate()
    const days = Array.from({ length: offset }, () => null)

    for (let day = 1; day <= totalDays; day++) {
      const date = new Date(state.viewYear, state.viewMonth, day)
      days.push({
        day,
        date,
        isStart: state.tempStart ? sameDay(state.tempStart, date) : false,
        isEnd: state.tempEnd ? sameDay(state.tempEnd, date) : false,
        inRange: !!(state.tempStart && state.tempEnd && date > state.tempStart && date < state.tempEnd),
      })
    }
    return days
  })

  function toggle() {
    state.open = !state.open
    if (state.open) {
      state.tempStart = state.start ? new Date(state.start) : null
      state.tempEnd = state.end ? new Date(state.end) : null
    }
  }

  function prevMonth() {
    const d = new Date(state.viewYear, state.viewMonth - 1, 1)
    state.viewYear = d.getFullYear()
    state.viewMonth = d.getMonth()
  }

  function nextMonth() {
    const d = new Date(state.viewYear, state.viewMonth + 1, 1)
    state.viewYear = d.getFullYear()
    state.viewMonth = d.getMonth()
  }

  function selectDay(day) {
    if (!day) return
    const selected = day.date
    if (!state.tempStart || state.tempEnd) {
      state.tempStart = selected
      state.tempEnd = null
    } else if (selected < state.tempStart) {
      state.tempEnd = state.tempStart
      state.tempStart = selected
    } else {
      state.tempEnd = selected
    }
  }

  function clear() {
    state.start = state.end = state.tempStart = state.tempEnd = null
  }

  function apply() {
    if (state.tempStart && state.tempEnd) {
      state.start = new Date(state.tempStart)
      state.end = new Date(state.tempEnd)
      state.open = false
    }
  }

  function reset() {
    state.enabled = false
    state.start = state.end = state.tempStart = state.tempEnd = null
    state.viewYear = 2026
    state.viewMonth = 4
    state.open = false
  }

  return { state, monthLabel, rangeText, calendarDays, toggle, prevMonth, nextMonth, selectDay, clear, apply, reset }
}

const soRange = createDateRange()
const adRange = createDateRange()

/* Tgl SO defaults to checked + the last 2 months, applied to the initial fetch too. */
const soDefaultEnd = new Date()
const soDefaultStart = new Date()
soDefaultStart.setMonth(soDefaultStart.getMonth() - 2)
soRange.state.enabled = true
soRange.state.start = soDefaultStart
soRange.state.end = soDefaultEnd
soRange.state.viewYear = soDefaultEnd.getFullYear()
soRange.state.viewMonth = soDefaultEnd.getMonth()

function handleDocumentClick(event) {
  if (!event.target.closest('[data-date-range]')) {
    soRange.state.open = false
    adRange.state.open = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick)
  // A saved filter state (from a prior "Terapkan Filter" this tab session) overrides the
  // Tgl SO 2-month default set above; otherwise that default stands.
  restoreFilterState()
  loadOrders(currentFilterPayload())
  saveFilterState()
  loadStatusOptions()
})
onUnmounted(() => document.removeEventListener('click', handleDocumentClick))
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-ink-500">Order Monitoring</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink-900 sm:text-3xl">Sales Order</h1>
        <p class="mt-1 text-sm text-ink-500">Monitoring proses order berdasarkan setiap departemen.</p>
      </div>
    </div>

    <!-- Filter Card -->
    <section class="rounded-lg border border-line-200 bg-white shadow-sm">
      <div class="border-b border-line-200 px-5 py-4 sm:px-6">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h2 class="text-sm font-semibold text-ink-900">Filter Data</h2>
            <p class="mt-1 text-xs text-ink-500">Tentukan parameter untuk menampilkan data sales order.</p>
          </div>
          <BaseButton variant="secondary" @click="resetAll">Reset</BaseButton>
        </div>
      </div>

      <div class="px-5 py-5 sm:px-6 sm:py-6">
        <!-- Row 1: Jenis Order + date ranges -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
          <div>
            <label for="jenisOrder" class="mb-1.5 block text-xs font-semibold text-ink-600">Jenis Order</label>
            <select
              id="jenisOrder"
              v-model="jenisOrderFilter"
              class="w-full rounded-lg border border-line-300 bg-white px-3 py-2.5 text-sm text-ink-900 outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
            >
              <option value="">Semua Jenis Order</option>
              <option>Stock</option>
              <option>Project</option>
              <option>Custom</option>
            </select>
          </div>

          <div v-for="range in [{ picker: soRange, label: 'Tgl SO' }, { picker: adRange, label: 'Tgl AD' }]" :key="range.label" data-date-range class="relative">
            <label class="mb-1.5 flex items-center gap-2 text-xs font-semibold text-ink-600">
              <input type="checkbox" v-model="range.picker.state.enabled" class="h-3.5 w-3.5 rounded border-line-300 text-brand-600 focus:ring-brand-500" />
              {{ range.label }}
            </label>
            <button
              type="button"
              class="flex w-full items-center justify-between rounded-lg border border-line-300 bg-white px-3 py-2.5 text-left text-sm outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
              :class="range.picker.state.start ? 'text-ink-900' : 'text-ink-500'"
              @click="range.picker.toggle()"
            >
              <span class="flex min-w-0 items-center gap-2">
                <svg class="h-4 w-4 shrink-0 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <rect x="3" y="4" width="18" height="17" rx="2"></rect>
                  <path d="M16 2v4M8 2v4M3 10h18"></path>
                </svg>
                <span class="truncate">{{ range.picker.rangeText.value }}</span>
              </span>
              <svg class="h-4 w-4 shrink-0 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="m6 9 6 6 6-6"></path>
              </svg>
            </button>

            <div
              v-if="range.picker.state.open"
              class="absolute left-0 top-[calc(100%+8px)] z-30 w-[320px] rounded-xl border border-line-200 bg-white p-4 shadow-xl"
            >
              <div class="mb-3 flex items-center justify-between">
                <button type="button" class="rounded-lg p-1.5 text-ink-500 hover:bg-slate-100" @click="range.picker.prevMonth()">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"></path></svg>
                </button>
                <div class="text-sm font-semibold text-ink-900">{{ range.picker.monthLabel.value }}</div>
                <button type="button" class="rounded-lg p-1.5 text-ink-500 hover:bg-slate-100" @click="range.picker.nextMonth()">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6 6-6"></path></svg>
                </button>
              </div>
              <div class="mb-1 grid grid-cols-7 text-center text-[10px] font-semibold uppercase tracking-wide text-ink-500">
                <span v-for="w in weekdayLabels" :key="w">{{ w }}</span>
              </div>
              <div class="grid grid-cols-7 gap-1">
                <template v-for="(day, index) in range.picker.calendarDays.value" :key="index">
                  <div v-if="!day"></div>
                  <button
                    v-else
                    type="button"
                    class="h-8 w-full rounded-lg text-xs font-medium hover:bg-slate-100"
                    :class="[
                      day.isStart || day.isEnd
                        ? 'bg-brand-600 font-semibold text-white hover:bg-brand-700'
                        : day.inRange
                          ? 'bg-slate-100 font-medium text-ink-900'
                          : 'text-ink-600',
                    ]"
                    @click="range.picker.selectDay(day)"
                  >
                    {{ day.day }}
                  </button>
                </template>
              </div>
              <div class="mt-3 flex items-center justify-between border-t border-line-200 pt-3">
                <button type="button" class="text-xs font-semibold text-ink-500 hover:text-ink-900" @click="range.picker.clear()">Hapus</button>
                <button type="button" class="rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700" @click="range.picker.apply()">Terapkan</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Row 2: Status Departemen -->
        <div class="mt-5 border-t border-line-200 pt-5">
          <div class="mb-3 flex items-center gap-2">
            <span class="h-1.5 w-1.5 rounded-full bg-ink-500"></span>
            <h3 class="text-xs font-semibold text-ink-600">Status Departemen</h3>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div v-for="dept in departments" :key="dept.key">
              <label :for="`${dept.key}Filter`" class="mb-1.5 block text-xs font-medium text-ink-500">{{ dept.label }}</label>
              <select
                :id="`${dept.key}Filter`"
                v-model="departmentFilters[dept.key]"
                class="w-full rounded-lg border border-line-300 bg-white px-3 py-2.5 text-sm text-ink-900 outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
              >
                <option value="semua">Semua</option>
                <option value="kosong">Kosong</option>
                <option v-for="opt in statusOptions[dept.key]" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Apply -->
        <div class="mt-5 flex justify-end border-t border-line-200 pt-5">
          <button
            type="button"
            class="w-full rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-100 sm:w-auto"
            :class="filterApplied ? 'bg-emerald-600' : 'bg-brand-600 hover:bg-brand-700'"
            @click="applyFilter"
          >
            {{ filterApplied ? 'Filter diterapkan' : 'Terapkan Filter' }}
          </button>
        </div>
      </div>
    </section>

    <BaseAlert v-if="loadError" variant="error">{{ loadError }}</BaseAlert>
    <BaseAlert v-if="statusUpdateError" variant="error">{{ statusUpdateError }}</BaseAlert>
    <BaseAlert v-if="statusUpdateSuccess" variant="success">{{ statusUpdateSuccess }}</BaseAlert>

    <!-- Table Section -->
    <section class="overflow-hidden rounded-lg border border-line-200 bg-white shadow-sm">
      <div class="flex flex-col gap-3 border-b border-line-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-semibold text-ink-900">Daftar Sales Order</h2>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-ink-600">
              {{ filteredOrders.length }} data
            </span>
          </div>
          <p class="mt-1 text-xs text-ink-500">Update status proses pada masing-masing departemen.</p>
        </div>

        <div class="flex w-full gap-2 sm:w-auto">
          <label class="relative block w-full sm:w-64">
            <span class="sr-only">Cari</span>
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="7"></circle>
              <path d="m20 20-3.5-3.5"></path>
            </svg>
            <input
              v-model="searchQuery"
              type="search"
              placeholder="Cari sales order..."
              class="w-full rounded-lg border border-line-300 bg-white py-2.5 pl-9 pr-3 text-sm text-ink-900 outline-none transition-colors placeholder:text-ink-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
            />
          </label>

          <select
            v-model="limitSelect"
            class="hidden rounded-lg border border-line-300 bg-white px-3 py-2.5 text-sm text-ink-600 outline-none sm:block"
          >
            <option v-for="opt in limitOptions" :key="opt" :value="opt">{{ opt }} / page</option>
          </select>
        </div>
      </div>

      <p v-if="loading" class="px-5 pb-5 text-sm text-ink-500">Memuat...</p>

      <div v-else class="px-3 pb-3 sm:px-5 sm:pb-5">
        <div class="sales-table-scroll overflow-x-auto rounded-xl border border-line-200">
          <table class="w-full min-w-[1180px] border-collapse text-sm">
            <thead>
              <tr class="bg-slate-100 text-left">
                <th class="w-14 border-b border-r border-line-200 px-4 py-3 text-center text-xs font-bold text-ink-600">No</th>
                <th class="w-64 border-b border-r border-line-200 px-4 py-3 text-xs font-bold text-ink-600">Sales</th>
                <th
                  v-for="dept in departments"
                  :key="dept.key"
                  :class="dept.width"
                  class="border-b border-r border-line-200 px-4 py-3 text-xs font-bold text-ink-600 last:border-r-0"
                >
                  {{ dept.label }}
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-line-200">
              <tr v-for="(order, index) in filteredOrders" :key="order.id" class="align-top transition-colors hover:bg-slate-50">
                <td class="border-r border-line-200 px-4 py-5 text-center font-medium text-ink-500">{{ index + 1 }}</td>
                <td class="border-r border-line-200 px-4 py-4">
                  <div class="font-semibold text-ink-900">{{ order.code }}</div>
                  <div class="mt-1 text-ink-600">Dibuat oleh: {{ order.createdBy }}</div>
                  <div class="mt-1 text-xs text-ink-500">Jenis Order : <span class="font-medium text-ink-600">{{ order.jenisOrder || '-' }}</span></div>
                  <div class="mt-1 text-xs text-ink-500">Tgl SO : <span class="font-medium text-ink-600">{{ order.tglSo }}</span></div>
                  <div class="mt-1 text-xs text-ink-500">Tgl AD : <span class="font-medium text-ink-600">{{ order.tglAd }}</span></div>
                </td>
                <td v-for="dept in departments" :key="dept.key" class="border-r border-line-200 px-3 py-5 last:border-r-0">
                  <div class="relative">
                    <select
                      :value="order.status[dept.key]"
                      :disabled="order.saving[dept.key]"
                      class="w-full rounded-lg border border-line-200 bg-white py-2 pl-3 pr-7 text-xs font-medium text-ink-600 outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500 disabled:opacity-60"
                      @change="handleStatusChange(order, dept.key, $event.target.value)"
                    >
                      <option value="">Pilih status</option>
                      <option v-for="opt in statusOptions[dept.key]" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                    </select>
                    <svg
                      v-if="order.saving[dept.key]"
                      class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 animate-spin text-brand-600"
                      viewBox="0 0 24 24"
                      fill="none"
                    >
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                  </div>
                </td>
              </tr>

              <tr v-if="!filteredOrders.length">
                <td :colspan="2 + departments.length" class="px-6 py-12 text-center">
                  <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-ink-500">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                      <circle cx="11" cy="11" r="7"></circle>
                      <path d="m20 20-3.5-3.5"></path>
                    </svg>
                  </div>
                  <p class="mt-3 text-sm font-semibold text-ink-900">Data tidak ditemukan</p>
                  <p class="mt-1 text-xs text-ink-500">Coba ubah kata kunci pencarian.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination (static — dataset always fits one page) -->
      <div class="flex flex-col gap-3 border-t border-line-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <p class="text-xs text-ink-500">
          Menampilkan <span class="font-semibold text-ink-600">{{ filteredOrders.length }}</span> dari
          <span class="font-semibold text-ink-600">{{ orders.length }}</span> data
        </p>
        <div class="flex items-center gap-1">
          <button type="button" disabled class="cursor-not-allowed rounded-lg border border-line-200 px-3 py-2 text-xs font-semibold text-ink-500 opacity-60">Sebelumnya</button>
          <button type="button" class="rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white">1</button>
          <button type="button" disabled class="cursor-not-allowed rounded-lg border border-line-200 px-3 py-2 text-xs font-semibold text-ink-500 opacity-60">Berikutnya</button>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Keep the table readable on smaller screens without destroying the layout. */
.sales-table-scroll {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
}

.sales-table-scroll::-webkit-scrollbar {
  height: 7px;
}

.sales-table-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 999px;
}
</style>
