import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useNotificationStore = defineStore('notification', () => {
  const notifications = ref([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const error = ref(null)

  function extractErrorMessage(err) {
    const data = err.response?.data

    if (data?.errors) {
      const firstField = Object.keys(data.errors)[0]
      return data.errors[firstField][0]
    }

    return data?.message || 'No se pudieron cargar las notificaciones.'
  }

  async function fetchNotifications({ silent = false } = {}) {
    if (!silent) {
      loading.value = true
    }
    error.value = null

    try {
      const { data } = await api.get('/notifications')
      notifications.value = data.notifications
      unreadCount.value = data.unread_count
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      if (!silent) {
        loading.value = false
      }
    }
  }

  async function markAllAsRead() {
    try {
      const { data } = await api.patch('/notifications/read-all')
      notifications.value = notifications.value.map((item) => ({ ...item, is_read: true }))
      unreadCount.value = data.unread_count
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    }
  }

  async function markAsRead(notificationId) {
    try {
      const { data } = await api.patch(`/notifications/${notificationId}/read`)

      const index = notifications.value.findIndex((item) => item.id === notificationId)

      if (index !== -1) {
        notifications.value[index] = data.notification
      }

      unreadCount.value = data.unread_count

      return data.notification
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    }
  }

  return {
    notifications,
    unreadCount,
    loading,
    error,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
  }
})
