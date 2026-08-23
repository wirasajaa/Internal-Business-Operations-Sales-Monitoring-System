import { defineStore } from 'pinia'
import api, { setUnauthorizedHandler } from '../services/api'
import { fetchNavigation } from '../services/menus'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    roles: [],
    permissions: [],
    accessToken: null,
    initializing: true,
    // Cached for the lifetime of this session only (cleared on logout/re-login).
    navigationMenu: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.accessToken && !!state.user,
    hasPermission: (state) => (name) => state.permissions.includes(name),
  },

  actions: {
    setSession({ access_token, user, roles, permissions }) {
      this.accessToken = access_token
      api.accessToken = access_token
      this.user = user
      this.roles = roles ?? []
      this.permissions = permissions ?? []
    },

    clearSession() {
      this.accessToken = null
      api.accessToken = null
      this.user = null
      this.roles = []
      this.permissions = []
      this.navigationMenu = null
    },

    async loadNavigationMenu({ force = false } = {}) {
      if (this.navigationMenu && !force) {
        return this.navigationMenu
      }
      this.navigationMenu = await fetchNavigation()
      return this.navigationMenu
    },

    async login(email, password) {
      const { data } = await api.post('/auth/login', { email, password })
      this.setSession(data)
    },

    async logout() {
      try {
        await api.post('/auth/logout')
      } finally {
        this.clearSession()
      }
    },

    async fetchCurrentUser() {
      const { data } = await api.get('/auth/me')
      this.user = data.user
      this.roles = data.roles ?? []
      this.permissions = data.permissions ?? []
    },

    async tryRestoreSession() {
      this.initializing = true
      try {
        const { data } = await api.post('/auth/refresh')
        this.setSession(data)
      } catch {
        this.clearSession()
      } finally {
        this.initializing = false
      }
    },

    bootstrapUnauthorizedHandler() {
      setUnauthorizedHandler(() => this.clearSession())
    },
  },
})
