import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useActivityStore = defineStore('activity', () => {
  const activity = ref(null)
  const activities = ref([])
  const lastSubmission = ref(null)
  const loading = ref(false)
  const submitting = ref(false)
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

  async function fetchCourseActivities(courseId) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get(`/courses/${courseId}/activities`)
      activities.value = data.activities
      return data.activities
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchActivity(activityId) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get(`/activities/${activityId}`)
      activity.value = data.activity
      lastSubmission.value = data.my_submission ?? null
      return data.activity
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function submitActivity(activityId, content, idempotencyKey) {
    submitting.value = true
    error.value = null
    success.value = null

    try {
      const { data } = await api.post(`/activities/${activityId}/submit`, {
        content,
        idempotency_key: idempotencyKey,
      })

      lastSubmission.value = data.submission
      success.value = 'Tu respuesta fue enviada correctamente.'
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      submitting.value = false
    }
  }

  function clearMessages() {
    error.value = null
    success.value = null
  }

  return {
    activity,
    activities,
    lastSubmission,
    loading,
    submitting,
    error,
    success,
    fetchCourseActivities,
    fetchActivity,
    submitActivity,
    clearMessages,
  }
})
