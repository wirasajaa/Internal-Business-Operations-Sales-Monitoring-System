import { defineStore } from 'pinia'
import api, { setUnauthorizedHandler } from '../services/api'
import { fetchNavigation } from '../services/menus'

// sessionStorage (not localStorage): survives a page refresh but clears when the tab/browser closes,
// matching the rest of this store's in-memory/cookie-based session pattern.
const NAVIGATION_MENU_STORAGE_KEY = 'auth.navigationMenu'

function readPersistedNavigationMenu() {
  try {
    const raw = sessionStorage.getItem(NAVIGATION_MENU_STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function persistNavigationMenu(menu) {
  try {
    sessionStorage.setItem(NAVIGATION_MENU_STORAGE_KEY, JSON.stringify(menu))
  } catch {
    // sessionStorage unavailable (e.g. private mode quota) — cache still works in-memory for this session.
  }
}

function clearPersistedNavigationMenu() {
  try {
    sessionStorage.removeItem(NAVIGATION_MENU_STORAGE_KEY)
  } catch {
    // ignore
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    roles: [],
    permissions: [],
    accessToken: null,
    initializing: true,
    // Persisted in sessionStorage so a browser refresh doesn't refetch it; cleared on logout/failed refresh.
    navigationMenu: readPersistedNavigationMenu(),
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
      clearPersistedNavigationMenu()
    },

    async loadNavigationMenu({ force = false } = {}) {
      if (this.navigationMenu && !force) {
        return this.navigationMenu
      }
      this.navigationMenu = await fetchNavigation()
      persistNavigationMenu(this.navigationMenu)
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
