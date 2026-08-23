import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  withCredentials: true,
})

let onUnauthorized = null

export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler
}

api.interceptors.request.use((config) => {
  const token = api.accessToken
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

let refreshPromise = null

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { config, response } = error
    const isAuthEndpoint = config?.url?.includes('/auth/login') || config?.url?.includes('/auth/refresh')

    if (response?.status === 401 && !config._retried && !isAuthEndpoint) {
      config._retried = true
      try {
        refreshPromise ??= api.post('/auth/refresh').finally(() => {
          refreshPromise = null
        })
        const { data } = await refreshPromise
        api.accessToken = data.access_token
        config.headers.Authorization = `Bearer ${data.access_token}`
        return api(config)
      } catch (refreshError) {
        onUnauthorized?.()
        return Promise.reject(refreshError)
      }
    }

    return Promise.reject(error)
  },
)

export default api
