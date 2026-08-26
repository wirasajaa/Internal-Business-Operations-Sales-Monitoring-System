import api from './api'

/**
 * `filters` maps onto sales.get_sales_order_for_update_status's argument shape:
 * find, is_date_so/start_so/end_so, is_date_ad/start_ad/end_ad. Only these are
 * enforced server-side (the function itself ignores its own filter args — see
 * SalesOrderController@index); empty/undefined values are simply omitted.
 */
export function fetchSalesOrders(filters = {}) {
  const params = {}
  for (const [key, value] of Object.entries(filters)) {
    if (value !== '' && value !== null && value !== undefined && value !== false) {
      params[key] = value
    }
  }
  return api.get('/sales/orders', { params }).then((res) => res.data.data)
}

/** Department status options, grouped by type (finance/ppic/design/purchasing/warehouse/leader_produksi). */
export function fetchOrderStatuses() {
  return api.get('/sales/order-statuses').then((res) => res.data.data)
}

/**
 * Persists one department's status for one order via sales.set_new_sales_order_for_
 * update_status. `type` is the DB type key (finance/ppic/design/purchasing/warehouse/
 * leader_produksi), `statusId` is a sales.master_sales_order_status id.
 */
export function updateOrderStatus(orderId, type, statusId) {
  return api
    .patch(`/sales/orders/${orderId}/status`, { type, status_id: statusId })
    .then((res) => res.data)
}
