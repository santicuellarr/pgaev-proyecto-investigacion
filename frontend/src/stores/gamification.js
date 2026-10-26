import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useGamificationStore = defineStore('gamification', () => {
  const leaderboard = ref([])
  const progress = ref(null)
  const history = ref([])
  const historyMeta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
  const loading = ref(false)
  const error = ref(null)

  const topThree = computed(() => leaderboard.value.slice(0, 3))
  const remainingEntries = computed(() => leaderboard.value.slice(3))

  function extractErrorMessage(err) {
    const data = err.response?.data

    if (data?.errors) {
      const firstField = Object.keys(data.errors)[0]
      return data.errors[firstField][0]
    }

    return data?.message || 'No se pudo cargar la clasificación.'
  }

  function getMyStats(userId) {
    return leaderboard.value.find((entry) => entry.user_id === userId) || null
  }

  function getLevelProgress(stats) {
    if (!stats) {
      return 0
    }

    const previousThreshold = (stats.current_level - 1) * 100
    const nextThreshold = stats.current_level * 100
    const range = nextThreshold - previousThreshold

    if (range <= 0) {
      return 100
    }

    return Math.min(100, Math.max(0, ((stats.total_points - previousThreshold) / range) * 100))
  }

  async function fetchLeaderboard(courseId) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get(`/courses/${courseId}/leaderboard`)
      leaderboard.value = data.leaderboard
      return data.leaderboard
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchProgress(courseId) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get('/me/progress', {
        params: courseId ? { course_id: courseId } : {},
      })
      progress.value = data
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      progress.value = null
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchHistory(params = {}) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get('/me/history', { params })
      history.value = data.history
      historyMeta.value = {
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

  return {
    leaderboard,
    progress,
    history,
    historyMeta,
    loading,
    error,
    topThree,
    remainingEntries,
    fetchLeaderboard,
    fetchProgress,
    fetchHistory,
    getMyStats,
    getLevelProgress,
  }
})
