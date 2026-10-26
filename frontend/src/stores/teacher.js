import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useTeacherStore = defineStore('teacher', () => {
  const courses = ref([])
  const course = ref(null)
  const enrollments = ref([])
  const activities = ref([])
  const pendingSubmissions = ref([])
  const submissions = ref([])
  const rewards = ref([])
  const badges = ref([])
  const sessions = ref([])
  const forumPosts = ref([])
  const analytics = ref(null)
  const overview = ref(null)
  const pendingRedemptions = ref([])
  const pendingRedemptionCount = ref(0)
  const toastMessage = ref('')
  const counts = ref({ enrolled: 0, activities: 0, pending_submissions: 0 })
  const loading = ref(false)
  const saving = ref(false)
  const error = ref(null)
  const success = ref(null)
  const fieldErrors = ref({})

  function extractErrorMessage(err) {
    const data = err.response?.data

    if (data?.errors) {
      fieldErrors.value = data.errors
      const firstField = Object.keys(data.errors)[0]
      return data.errors[firstField][0]
    }

    fieldErrors.value = {}
    return data?.message || 'Ocurrió un error inesperado. Intenta de nuevo.'
  }

  function clearMessages() {
    error.value = null
    success.value = null
    fieldErrors.value = {}
    toastMessage.value = ''
  }

  function showToast(message) {
    toastMessage.value = message
    window.setTimeout(() => {
      if (toastMessage.value === message) {
        toastMessage.value = ''
      }
    }, 4000)
  }

  async function fetchCourses() {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get('/courses')
      courses.value = data.courses
      return data.courses
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchCourse(courseId) {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get(`/courses/${courseId}`)
      course.value = data.course
      counts.value = data.counts
      enrollments.value = data.enrollments
      activities.value = data.activities
      pendingSubmissions.value = data.pending_submissions
      rewards.value = data.rewards
      badges.value = data.badges
      sessions.value = data.sessions || []
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createCourse(payload) {
    saving.value = true
    error.value = null
    success.value = null
    fieldErrors.value = {}

    try {
      const { data } = await api.post('/courses', payload)
      courses.value.unshift(data.course)
      success.value = `Curso creado. Código: ${data.course.invite_code}`
      return data.course
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateCourse(courseId, payload) {
    saving.value = true
    error.value = null
    success.value = null
    fieldErrors.value = {}

    try {
      const { data } = await api.put(`/courses/${courseId}`, payload)
      course.value = { ...course.value, ...data.course }
      const index = courses.value.findIndex((item) => item.id === courseId)
      if (index !== -1) {
        courses.value[index] = { ...courses.value[index], ...data.course }
      }
      success.value = 'Curso actualizado.'
      return data.course
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateCourseStatus(courseId, status) {
    saving.value = true
    error.value = null

    try {
      const { data } = await api.patch(`/courses/${courseId}/status`, { status })
      course.value = { ...course.value, ...data.course }
      return data.course
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function createActivity(courseId, payload) {
    saving.value = true
    error.value = null
    success.value = null
    fieldErrors.value = {}

    try {
      const { data } = await api.post(`/courses/${courseId}/activities`, payload)
      activities.value.unshift(data.activity)
      success.value = 'Actividad creada.'
      return data.activity
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateActivity(activityId, payload) {
    saving.value = true
    error.value = null
    success.value = null
    fieldErrors.value = {}

    try {
      const { data } = await api.put(`/activities/${activityId}`, payload)
      const index = activities.value.findIndex((item) => item.id === activityId)
      if (index !== -1) {
        activities.value[index] = data.activity
      }
      success.value = 'Actividad actualizada.'
      return data.activity
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function publishActivity(activityId, isPublished = true) {
    saving.value = true
    error.value = null

    try {
      const { data } = await api.patch(`/activities/${activityId}/publish`, {
        is_published: isPublished,
      })
      const index = activities.value.findIndex((item) => item.id === activityId)
      if (index !== -1) {
        activities.value[index] = data.activity
      }
      success.value = isPublished ? 'Actividad publicada.' : 'Actividad despublicada.'
      return data.activity
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function deleteActivity(activityId) {
    saving.value = true
    error.value = null

    try {
      await api.delete(`/activities/${activityId}`)
      activities.value = activities.value.filter((item) => item.id !== activityId)
      success.value = 'Actividad eliminada.'
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function exportCourseCsv(courseId, type = null) {
    error.value = null

    try {
      const response = await api.get(`/courses/${courseId}/analytics/export`, {
        responseType: 'blob',
        params: type ? { type } : {},
      })

      const blob = new Blob([response.data], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      const suffix = type ? `-${type}` : ''
      link.setAttribute('download', `analytics-curso-${courseId}${suffix}.csv`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)

      return true
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    }
  }

  async function fetchAnalytics(courseId) {
    const { data } = await api.get(`/courses/${courseId}/analytics`)
    analytics.value = data
    return data
  }

  async function fetchOverview() {
    const { data } = await api.get('/teacher/overview')
    overview.value = data
    return data
  }

  async function fetchStudentHistory(courseId, userId) {
    const { data } = await api.get(`/courses/${courseId}/students/${userId}/history`)
    return data.history
  }

  async function fetchCourseSubmissions() {
    const rows = []

    for (const activity of activities.value) {
      const { data } = await api.get(`/activities/${activity.id}/submissions`)
      for (const submission of data.submissions) {
        rows.push({
          ...submission,
          activity,
        })
      }
    }

    submissions.value = rows.sort((a, b) => new Date(b.submitted_at) - new Date(a.submitted_at))
    return submissions.value
  }

  async function gradeSubmission(submissionId, payload) {
    saving.value = true
    error.value = null

    try {
      const { data } = await api.patch(`/submissions/${submissionId}/grade`, payload)
      showToast(`+${data.points_awarded} pts otorgados`)
      const index = submissions.value.findIndex((item) => item.id === submissionId)
      if (index !== -1) {
        submissions.value[index] = {
          ...submissions.value[index],
          ...data.submission,
        }
      }
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function regenerateAiFeedback(submissionId) {
    const { data } = await api.post(`/submissions/${submissionId}/ai-feedback/regenerate`)
    const index = submissions.value.findIndex((item) => item.id === submissionId)
    if (index !== -1) {
      submissions.value[index] = {
        ...submissions.value[index],
        ...data.submission,
      }
    }
    return data.submission
  }

  async function createReward(courseId, payload) {
    saving.value = true
    error.value = null
    try {
      const { data } = await api.post(`/courses/${courseId}/rewards`, payload)
      rewards.value.push(data.reward)
      success.value = 'Recompensa creada.'
      return data.reward
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateReward(rewardId, payload) {
    saving.value = true
    error.value = null
    try {
      const { data } = await api.put(`/rewards/${rewardId}`, payload)
      const index = rewards.value.findIndex((item) => item.id === rewardId)
      if (index !== -1) {
        rewards.value[index] = data.reward
      }
      success.value = 'Recompensa actualizada.'
      return data.reward
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function deleteReward(rewardId) {
    saving.value = true
    error.value = null
    try {
      await api.delete(`/rewards/${rewardId}`)
      rewards.value = rewards.value.filter((item) => item.id !== rewardId)
      success.value = 'Recompensa eliminada.'
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function createBadge(courseId, payload) {
    saving.value = true
    error.value = null
    try {
      const { data } = await api.post(`/courses/${courseId}/badges`, payload)
      badges.value.push(data.badge)
      success.value = 'Insignia creada.'
      return data.badge
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateBadge(courseId, badgeId, payload) {
    saving.value = true
    error.value = null
    try {
      const { data } = await api.put(`/courses/${courseId}/badges/${badgeId}`, payload)
      const index = badges.value.findIndex((item) => item.id === badgeId)
      if (index !== -1) {
        badges.value[index] = data.badge
      }
      success.value = 'Insignia actualizada.'
      return data.badge
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function deleteBadge(courseId, badgeId) {
    saving.value = true
    error.value = null
    try {
      await api.delete(`/courses/${courseId}/badges/${badgeId}`)
      badges.value = badges.value.filter((item) => item.id !== badgeId)
      success.value = 'Insignia eliminada.'
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function awardBadge(badgeId, userId) {
    saving.value = true
    error.value = null
    try {
      const { data } = await api.post(`/badges/${badgeId}/award/${userId}`)
      success.value = 'Insignia otorgada.'
      return data.user_badge
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function fetchPendingRedemptions() {
    const { data } = await api.get('/redemptions/pending')
    pendingRedemptions.value = data.redemptions
    pendingRedemptionCount.value = data.pending_count
    return data.redemptions
  }

  async function approveRedemption(id) {
    await api.post(`/redemptions/${id}/approve`)
    await fetchPendingRedemptions()
  }

  async function rejectRedemption(id, reason) {
    await api.post(`/redemptions/${id}/reject`, { reason })
    await fetchPendingRedemptions()
  }

  async function createSession(courseId, payload) {
    saving.value = true
    error.value = null
    try {
      const { data } = await api.post(`/courses/${courseId}/sessions`, payload)
      sessions.value = [data.session, ...sessions.value]
      success.value = 'Sesión creada.'
      return data.session
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function updateSession(sessionId, payload) {
    saving.value = true
    error.value = null
    try {
      const { data } = await api.put(`/sessions/${sessionId}`, payload)
      const index = sessions.value.findIndex((item) => item.id === sessionId)
      if (index !== -1) {
        sessions.value[index] = data.session
      }
      success.value = 'Sesión actualizada.'
      return data.session
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function deleteSession(sessionId) {
    saving.value = true
    error.value = null
    try {
      await api.delete(`/sessions/${sessionId}`)
      sessions.value = sessions.value.filter((item) => item.id !== sessionId)
      success.value = 'Sesión eliminada.'
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function saveAttendance(sessionId, attendances) {
    saving.value = true
    error.value = null
    try {
      const { data } = await api.put(`/sessions/${sessionId}/attendance`, { attendances })
      const index = sessions.value.findIndex((item) => item.id === sessionId)
      if (index !== -1) {
        sessions.value[index] = data.session
      }
      const total = (data.points_awarded || []).reduce((sum, row) => sum + Number(row.points || 0), 0)
      if (total > 0) {
        showToast(`+${total} pts por asistencia`)
      } else {
        success.value = 'Asistencia guardada.'
      }
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function fetchForumPosts(activityId) {
    const { data } = await api.get(`/activities/${activityId}/posts`)
    forumPosts.value = data.posts
    return data.posts
  }

  return {
    courses,
    course,
    enrollments,
    activities,
    pendingSubmissions,
    submissions,
    rewards,
    badges,
    sessions,
    forumPosts,
    analytics,
    overview,
    pendingRedemptions,
    pendingRedemptionCount,
    toastMessage,
    counts,
    loading,
    saving,
    error,
    success,
    fieldErrors,
    clearMessages,
    showToast,
    fetchCourses,
    fetchCourse,
    createCourse,
    updateCourse,
    updateCourseStatus,
    createActivity,
    updateActivity,
    publishActivity,
    deleteActivity,
    exportCourseCsv,
    fetchCourseSubmissions,
    gradeSubmission,
    regenerateAiFeedback,
    createReward,
    updateReward,
    deleteReward,
    createBadge,
    updateBadge,
    deleteBadge,
    awardBadge,
    fetchPendingRedemptions,
    approveRedemption,
    rejectRedemption,
    createSession,
    updateSession,
    deleteSession,
    saveAttendance,
    fetchForumPosts,
    fetchAnalytics,
    fetchOverview,
    fetchStudentHistory,
  }
})
