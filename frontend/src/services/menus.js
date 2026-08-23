import api from './api'

export function fetchMenus() {
  return api.get('/menus').then((res) => res.data.data)
}

export function fetchNavigation() {
  return api.get('/menus/navigation').then((res) => res.data.data)
}

export function fetchAvailablePermissions() {
  return api.get('/menus/permissions').then((res) => res.data.data)
}

export function createMenu(payload) {
  return api.post('/menus', payload).then((res) => res.data.data)
}

export function updateMenu(id, payload) {
  return api.put(`/menus/${id}`, payload).then((res) => res.data.data)
}

export function deleteMenu(id) {
  return api.delete(`/menus/${id}`)
}
