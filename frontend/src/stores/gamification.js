import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useGamificationStore = defineStore('gamification', () => {
  const leaderboard = ref([])
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

  return {
    leaderboard,
    loading,
    error,
    topThree,
    remainingEntries,
    fetchLeaderboard,
    getMyStats,
    getLevelProgress,
  }
})
