import api from './api'

export function fetchRoles() {
  return api.get('/roles').then((res) => res.data.data)
}

export function fetchAllPermissions() {
  return api.get('/roles/permissions').then((res) => res.data.data)
}

export function createRole(payload) {
  return api.post('/roles', payload).then((res) => res.data.data)
}

export function updateRole(id, payload) {
  return api.put(`/roles/${id}`, payload).then((res) => res.data.data)
}

export function deleteRole(id) {
  return api.delete(`/roles/${id}`)
}
