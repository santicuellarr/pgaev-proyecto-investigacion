import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useCourseStore = defineStore('course', () => {
  const courses = ref([])
  const loading = ref(false)
  const error = ref(null)
  const success = ref(null)
  const lastCreatedCourse = ref(null)

  function extractErrorMessage(err) {
    const data = err.response?.data

    if (data?.errors) {
      const firstField = Object.keys(data.errors)[0]
      return data.errors[firstField][0]
    }

    return data?.message || 'Ocurrió un error inesperado. Intenta de nuevo.'
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

  async function createCourse(payload) {
    loading.value = true
    error.value = null
    success.value = null

    try {
      const { data } = await api.post('/courses', payload)
      lastCreatedCourse.value = data.course
      courses.value.unshift(data.course)
      return data.course
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function enrollInCourse(inviteCode) {
    loading.value = true
    error.value = null
    success.value = null

    try {
      const { data } = await api.post('/courses/enroll', {
        invite_code: inviteCode.toUpperCase(),
      })

      const alreadyListed = courses.value.some((course) => course.id === data.course.id)

      if (!alreadyListed) {
        courses.value.unshift(data.course)
      }

      success.value = `Te has unido exitosamente al curso "${data.course.title}".`
      return data
    } catch (err) {
      error.value = extractErrorMessage(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearMessages() {
    error.value = null
    success.value = null
  }

  async function exportCourseCsv(courseId) {
    error.value = null

    try {
      const response = await api.get(`/courses/${courseId}/analytics/export`, {
        responseType: 'blob',
      })

      const blob = new Blob([response.data], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `analytics-curso-${courseId}.csv`)
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

  return {
    courses,
    loading,
    error,
    success,
    lastCreatedCourse,
    fetchCourses,
    createCourse,
    enrollInCourse,
    exportCourseCsv,
    clearMessages,
  }
})
