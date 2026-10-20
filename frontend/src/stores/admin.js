import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAdminStore = defineStore('admin', () => {
  const users = ref([])
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
  const overview = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const success = ref(null)

  function extractErrorMessage(err) {
    const data = err.response?.data

    if (data?.errors) {
      const firstField = Object.keys(data.errors)[0]
      return data.errors[firstField][0]
    }

    return data?.message || 'No se pudo completar la operación de administración.'
  }

  async function fetchOverview() {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get('/admin/overview')
      overview.value = data
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchUsers(params = {}) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get('/admin/users', { params })
      users.value = data.users
      meta.value = {
        current_page: data.current_page,
        last_page: data.last_page,
        total: data.total,
        per_page: data.per_page,
      }
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateRole(userId, role) {
    error.value = null
    success.value = null

    try {
      const { data } = await api.patch(`/admin/users/${userId}/role`, { role })
      const index = users.value.findIndex((item) => item.id === userId)
      if (index !== -1) {
        users.value[index] = data.user
      }
      success.value = `Rol actualizado a ${role}.`
      return data.user
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    }
  }

  async function updateStatus(userId, status) {
    error.value = null
    success.value = null

    try {
      const { data } = await api.patch(`/admin/users/${userId}/status`, { status })
      const index = users.value.findIndex((item) => item.id === userId)
      if (index !== -1) {
        users.value[index] = data.user
      }
      success.value = status === 'suspended' ? 'Usuario suspendido.' : 'Usuario reactivado.'
      return data.user
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    }
  }

  return {
    users,
    meta,
    overview,
    loading,
    error,
    success,
    fetchOverview,
    fetchUsers,
    updateRole,
    updateStatus,
  }
})
