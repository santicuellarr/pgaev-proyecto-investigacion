<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import NotificationBell from '@/components/NotificationBell.vue'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/course'

const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()

const showModal = ref(false)
const exportingCourseId = ref(null)

const form = reactive({
  title: '',
  description: '',
})

onMounted(async () => {
  await courseStore.fetchCourses()
})

function openModal() {
  courseStore.clearMessages()
  courseStore.lastCreatedCourse = null
  form.title = ''
  form.description = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleCreateCourse() {
  try {
    await courseStore.createCourse({
      title: form.title,
      description: form.description || null,
    })
    form.title = ''
    form.description = ''
  } catch {
    // El error se muestra desde courseStore.error
  }
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

async function handleExportCsv(courseId) {
  if (exportingCourseId.value) {
    return
  }

  exportingCourseId.value = courseId

  try {
    await courseStore.exportCourseCsv(courseId)
  } catch {
    // El error se muestra desde courseStore.error
  } finally {
    exportingCourseId.value = null
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-slate-900">PGAEV — Panel Docente</h1>
          <p v-if="auth.user" class="text-sm text-slate-500">
            {{ auth.user.name }}
            <span class="ml-2 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">
              {{ auth.user.role }}
            </span>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <NotificationBell />
          <button
            type="button"
            class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-semibold transition"
            @click="openModal"
          >
            + Nuevo curso
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition"
            @click="handleLogout"
          >
            Cerrar sesión
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">
      <div
        v-if="courseStore.error && !showModal"
        class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        role="alert"
      >
        {{ courseStore.error }}
      </div>

      <div
        v-if="courseStore.lastCreatedCourse"
        class="mb-6 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-4"
      >
        <p class="text-sm font-medium text-emerald-800">¡Curso creado exitosamente!</p>
        <p class="mt-1 text-emerald-700">
          Comparte este código con tus estudiantes:
        </p>
        <p class="mt-2 text-3xl font-bold tracking-[0.3em] text-emerald-900 font-mono">
          {{ courseStore.lastCreatedCourse.invite_code }}
        </p>
      </div>

      <div v-if="courseStore.loading && !courseStore.courses.length" class="text-slate-500">
        Cargando cursos...
      </div>

      <div v-else-if="!courseStore.courses.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
        <p class="text-slate-600">Aún no has creado ningún curso.</p>
        <button
          type="button"
          class="mt-4 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-semibold transition"
          @click="openModal"
        >
          Crear tu primer curso
        </button>
      </div>

      <div v-else class="grid gap-4 md:grid-cols-2">
        <article
          v-for="course in courseStore.courses"
          :key="course.id"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">{{ course.title }}</h2>
              <p class="mt-1 text-sm text-slate-600 line-clamp-2">
                {{ course.description || 'Sin descripción' }}
              </p>
            </div>
            <span
              class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
              :class="course.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
            >
              {{ course.is_active ? 'Activo' : 'Inactivo' }}
            </span>
          </div>
          <div class="mt-4 rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3">
            <p class="text-xs font-medium uppercase tracking-wide text-indigo-600">Código de invitación</p>
            <p class="mt-1 text-xl font-bold tracking-widest text-indigo-900 font-mono">
              {{ course.invite_code }}
            </p>
          </div>
          <button
            type="button"
            class="mt-4 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 transition"
            :disabled="exportingCourseId === course.id"
            @click="handleExportCsv(course.id)"
          >
            {{ exportingCourseId === course.id ? 'Exportando...' : 'Exportar Analíticas (.CSV)' }}
          </button>
        </article>
      </div>
    </main>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
      @click.self="closeModal"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-5">
          <h3 class="text-lg font-semibold text-slate-900">Crear nuevo curso</h3>
          <button
            type="button"
            class="text-slate-400 hover:text-slate-600"
            @click="closeModal"
          >
            ✕
          </button>
        </div>

        <div
          v-if="courseStore.error"
          class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
          role="alert"
        >
          {{ courseStore.error }}
        </div>

        <form class="space-y-4" @submit.prevent="handleCreateCourse">
          <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1.5">
              Título del curso
            </label>
            <input
              id="title"
              v-model="form.title"
              type="text"
              required
              class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="Ej. Gamificación en Educación"
            />
          </div>

          <div>
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">
              Descripción
            </label>
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="Describe el objetivo del curso..."
            />
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              @click="closeModal"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="courseStore.loading"
              class="rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white px-4 py-2 text-sm font-semibold transition"
            >
              {{ courseStore.loading ? 'Creando...' : 'Crear curso' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
