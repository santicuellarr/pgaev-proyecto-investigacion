import axios from 'axios'

const apiBase = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'

const api = axios.create({
  baseURL: apiBase,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

const TOKEN_KEY = 'token'
const USER_KEY = 'user'

let refreshPromise = null

function isAuthRequest(config) {
  const url = config?.url || ''

  return url.includes('/login')
    || url.includes('/register')
    || url.includes('/token/refresh')
}

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const original = error.config

    if (error.response?.status !== 401 || !original || original._retry || isAuthRequest(original)) {
      return Promise.reject(error)
    }

    original._retry = true

    try {
      if (!refreshPromise) {
        refreshPromise = axios.post(
          `${apiBase}/token/refresh`,
          {},
          {
            headers: {
              Accept: 'application/json',
              Authorization: `Bearer ${localStorage.getItem(TOKEN_KEY) || ''}`,
            },
          },
        ).then((response) => {
          const newToken = response.data.token
          localStorage.setItem(TOKEN_KEY, newToken)

          if (response.data.user) {
            localStorage.setItem(USER_KEY, JSON.stringify(response.data.user))
          }

          return newToken
        }).finally(() => {
          refreshPromise = null
        })
      }

      const newToken = await refreshPromise
      original.headers.Authorization = `Bearer ${newToken}`

      return api(original)
    } catch {
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(USER_KEY)

      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }

      return Promise.reject(error)
    }
  },
)

export default api
