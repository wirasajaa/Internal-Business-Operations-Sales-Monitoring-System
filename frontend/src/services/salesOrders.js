import api from './api'

export function fetchSalesOrders() {
  return api.get('/sales/orders').then((res) => res.data.data)
}
