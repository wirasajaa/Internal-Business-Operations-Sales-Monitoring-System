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
