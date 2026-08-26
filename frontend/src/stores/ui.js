import { defineStore } from 'pinia'

// Global UI flags for the loading overlay (route transitions + logout).
// Kept separate from the auth store since this is presentational-only state.
export const useUiStore = defineStore('ui', {
  state: () => ({
    isRouteLoading: false,
    isLoggingOut: false,
  }),

  getters: {
    isLoading: (state) => state.isRouteLoading || state.isLoggingOut,
  },
})
