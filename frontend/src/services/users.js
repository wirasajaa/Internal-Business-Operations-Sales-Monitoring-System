import api from './api'

export function fetchUsers() {
  return api.get('/users').then((res) => res.data.data)
}

// No createUser() — users are no longer created manually here; identity comes
// from bpms.users, auto-provisioned on first login (see requirement-conflicts/
// create-user-disabled-2026-08-24.md under dev-doc/user-role-permission-management/).

export function updateUser(id, payload) {
  return api.put(`/users/${id}`, payload).then((res) => res.data.data)
}

export function activateUser(id) {
  return api.patch(`/users/${id}/activate`).then((res) => res.data.data)
}

export function deactivateUser(id) {
  return api.patch(`/users/${id}/deactivate`).then((res) => res.data.data)
}
