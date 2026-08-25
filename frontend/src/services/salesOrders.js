import api from './api'

export function fetchSalesOrders() {
  return api.get('/sales/orders').then((res) => res.data.data)
}

/** Department status options, grouped by type (finance/ppic/design/purchasing/warehouse/leader_produksi). */
export function fetchOrderStatuses() {
  return api.get('/sales/order-statuses').then((res) => res.data.data)
}
