<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import BaseButton from '../components/base/BaseButton.vue'
import BaseAlert from '../components/base/BaseAlert.vue'
import { fetchSalesOrders, fetchOrderStatuses } from '../services/salesOrders'

/*
 * The "Sales" column is wired to real data from sales.get_sales_order_for_update_status.
 * Department status columns (finance..leader) are left unset — the source function
 * hardcodes them to null at the SQL level; populating them is a separate future slice.
 */
const orders = ref([])
const loading = ref(true)
const loadError = ref('')

function mapOrder(row) {
  return {
    id: row.id,
    code: row.id,
    createdBy: row.created_by,
    jenisOrder: row.jenis_order,
    tglSo: row.tgl_so ? formatDate(new Date(row.tgl_so)) : '-',
    tglAd: row.tgl_ad ? formatDate(new Date(row.tgl_ad)) : '-',
    status: reactive({ finance: '', ppic: '', design: '', purchasing: '', warehouse: '', leader: '' }),
  }
}

async function loadOrders() {
  loading.value = true
  loadError.value = ''
  try {
    const data = await fetchSalesOrders()
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
 * list is an array of {id, name, sort_order}; the select's value is the status name.
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

/* Filter card state — decorative only (matches the source mockup: only search actually filters). */
const jenisOrderFilter = ref('')
const departmentFilters = reactive({ finance: '', ppic: '', design: '', purchasing: '', warehouse: '', leader: '' })
const filterApplied = ref(false)

function applyFilter() {
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
 * Reset clears the filter card, the date pickers, the search box, AND every row's
 * department status back to blank — matching the mockup, whose reset handler
 * selects every <select> on the page (filters and per-row status cells alike).
 */
function resetAll() {
  jenisOrderFilter.value = ''
  for (const key of Object.keys(departmentFilters)) departmentFilters[key] = ''
  searchQuery.value = ''
  soRange.reset()
  adRange.reset()
  for (const order of orders.value) {
    for (const key of Object.keys(order.status)) order.status[key] = ''
  }
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
    state.start = state.end = state.tempStart = state.tempEnd = null
    state.viewYear = 2026
    state.viewMonth = 4
    state.open = false
  }

  return { state, monthLabel, rangeText, calendarDays, toggle, prevMonth, nextMonth, selectDay, clear, apply, reset }
}

const soRange = createDateRange()
const adRange = createDateRange()

function handleDocumentClick(event) {
  if (!event.target.closest('[data-date-range]')) {
    soRange.state.open = false
    adRange.state.open = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick)
  loadOrders()
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
            <label class="mb-1.5 block text-xs font-semibold text-ink-600">{{ range.label }}</label>
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
                <option value="">Semua</option>
                <option v-for="opt in statusOptions[dept.key]" :key="opt.id" :value="opt.name">{{ opt.name }}</option>
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
                  <select
                    v-model="order.status[dept.key]"
                    class="w-full rounded-lg border border-line-200 bg-white px-3 py-2 text-xs font-medium text-ink-600 outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                  >
                    <option value="">Pilih status</option>
                    <option v-for="opt in statusOptions[dept.key]" :key="opt.id" :value="opt.name">{{ opt.name }}</option>
                  </select>
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
