import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useRewardStore = defineStore('reward', () => {
  const rewards = ref([])
  const badges = ref([])
  const loading = ref(false)
  const redeeming = ref(false)
  const error = ref(null)
  const success = ref(null)

  function extractErrorMessage(err) {
    const data = err.response?.data

    if (data?.errors) {
      const firstField = Object.keys(data.errors)[0]
      return data.errors[firstField][0]
    }

    return data?.message || 'Ocurrió un error inesperado. Intenta de nuevo.'
  }

  async function fetchRewards(courseId) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get(`/courses/${courseId}/rewards`)
      rewards.value = data.rewards
      return data.rewards
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchMyBadges(courseId) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get(`/courses/${courseId}/my-badges`)
      badges.value = data.badges
      return data.badges
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function redeemReward(rewardId, idempotencyKey) {
    redeeming.value = true
    error.value = null
    success.value = null

    try {
      const { data } = await api.post(`/rewards/${rewardId}/redeem`, {
        idempotency_key: idempotencyKey,
      })

      success.value = '¡Recompensa canjeada exitosamente!'
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      redeeming.value = false
    }
  }

  function clearMessages() {
    error.value = null
    success.value = null
  }

  return {
    rewards,
    badges,
    loading,
    redeeming,
    error,
    success,
    fetchRewards,
    fetchMyBadges,
    redeemReward,
    clearMessages,
  }
})
