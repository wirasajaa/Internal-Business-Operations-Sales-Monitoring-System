import api from './api'

export function fetchPermissions() {
  return api.get('/permissions').then((res) => res.data.data)
}

export function createPermission(payload) {
  return api.post('/permissions', payload).then((res) => res.data.data)
}

export function updatePermission(id, payload) {
  return api.put(`/permissions/${id}`, payload).then((res) => res.data.data)
}

export function deletePermission(id) {
  return api.delete(`/permissions/${id}`)
}
