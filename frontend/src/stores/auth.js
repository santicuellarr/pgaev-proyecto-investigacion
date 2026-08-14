import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

const TOKEN_KEY = 'token'
const USER_KEY = 'user'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(TOKEN_KEY) || '')
  const user = ref(JSON.parse(localStorage.getItem(USER_KEY) || 'null'))
  const error = ref(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value))

  function persistSession(newToken, newUser) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem(TOKEN_KEY, newToken)
    localStorage.setItem(USER_KEY, JSON.stringify(newUser))
  }

  function clearSession() {
    token.value = ''
    user.value = null
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  }

  function extractErrorMessage(err) {
    const data = err.response?.data

    if (data?.errors) {
      const firstField = Object.keys(data.errors)[0]
      return data.errors[firstField][0]
    }

    return data?.message || 'Ocurrió un error inesperado. Intenta de nuevo.'
  }

  async function register(payload) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.post('/register', payload)
      persistSession(data.token, data.user)
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function login(payload) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.post('/login', payload)
      persistSession(data.token, data.user)
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    loading.value = true
    error.value = null

    try {
      if (token.value) {
        await api.post('/logout')
      }
    } catch (err) {
      error.value = extractErrorMessage(err)
    } finally {
      clearSession()
      loading.value = false
    }
  }

  return {
    token,
    user,
    error,
    loading,
    isAuthenticated,
    register,
    login,
    logout,
    clearSession,
  }
})
