import api from './api'

export function fetchUsers() {
  return api.get('/users').then((res) => res.data.data)
}

export function createUser(payload) {
  return api.post('/users', payload).then((res) => res.data.data)
}

export function updateUser(id, payload) {
  return api.put(`/users/${id}`, payload).then((res) => res.data.data)
}

export function activateUser(id) {
  return api.patch(`/users/${id}/activate`).then((res) => res.data.data)
}

export function deactivateUser(id) {
  return api.patch(`/users/${id}/deactivate`).then((res) => res.data.data)
}
