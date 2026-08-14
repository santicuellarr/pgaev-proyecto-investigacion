<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import StudentAppHeader from '@/components/StudentAppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/course'

const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()

const inviteCode = ref('')

onMounted(async () => {
  await courseStore.fetchCourses()
})

async function handleEnroll() {
  courseStore.clearMessages()

  try {
    await courseStore.enrollInCourse(inviteCode.value.trim())
    inviteCode.value = ''
  } catch {
    // El error se muestra desde courseStore.error
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <StudentAppHeader
      title="PGAEV — Panel Estudiante"
      :subtitle="auth.user ? auth.user.name : ''"
    />

    <main class="max-w-6xl mx-auto px-4 py-8 space-y-8">
      <section class="rounded-2xl border border-indigo-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Unirse a un curso</h2>
        <p class="mt-1 text-sm text-slate-600">
          Ingresa el código de invitación de 6 caracteres que te compartió tu docente.
        </p>

        <div
          v-if="courseStore.success"
          class="mt-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700"
          role="status"
        >
          {{ courseStore.success }}
        </div>

        <div
          v-if="courseStore.error"
          class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
          role="alert"
        >
          {{ courseStore.error }}
        </div>

        <form class="mt-5 flex flex-col sm:flex-row gap-3" @submit.prevent="handleEnroll">
          <input
            v-model="inviteCode"
            type="text"
            maxlength="6"
            required
            class="flex-1 rounded-xl border-2 border-indigo-200 bg-indigo-50 px-4 py-3 text-center text-2xl font-bold tracking-[0.4em] uppercase text-indigo-900 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
            placeholder="K8P2W9"
            @input="inviteCode = inviteCode.toUpperCase().replace(/[^A-Z0-9]/g, '')"
          />
          <button
            type="submit"
            :disabled="courseStore.loading || inviteCode.length !== 6"
            class="rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-3 text-sm font-semibold transition whitespace-nowrap"
          >
            {{ courseStore.loading ? 'Uniéndose...' : 'Unirse al Curso' }}
          </button>
        </form>
      </section>

      <section>
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Mis cursos inscritos</h2>

        <div v-if="courseStore.loading && !courseStore.courses.length" class="text-slate-500">
          Cargando cursos...
        </div>

        <div
          v-else-if="!courseStore.courses.length"
          class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
        >
          <p class="text-slate-600">Aún no estás inscrito en ningún curso.</p>
          <p class="mt-1 text-sm text-slate-500">Usa un código de invitación para comenzar.</p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2">
          <article
            v-for="course in courseStore.courses"
            :key="course.id"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <h3 class="text-lg font-semibold text-slate-900">{{ course.title }}</h3>
                <p class="mt-1 text-sm text-slate-600 line-clamp-2">
                  {{ course.description || 'Sin descripción' }}
                </p>
              </div>
              <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">
                Inscrito
              </span>
            </div>
            <p
              v-if="course.enrollments?.[0]?.enrolled_at"
              class="mt-4 text-xs text-slate-500"
            >
              Inscrito el {{ new Date(course.enrollments[0].enrolled_at).toLocaleDateString() }}
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
              <button
                type="button"
                class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-semibold transition"
                @click="router.push({ name: 'course-activities', params: { courseId: course.id } })"
              >
                Ver actividades
              </button>
              <button
                type="button"
                class="rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 px-4 py-2 text-sm font-semibold transition"
                @click="router.push({ name: 'course-rewards', params: { courseId: course.id } })"
              >
                Tienda
              </button>
              <button
                type="button"
                class="rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 px-4 py-2 text-sm font-semibold transition"
                @click="router.push({ name: 'course-leaderboard', params: { courseId: course.id } })"
              >
                Clasificación
              </button>
            </div>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>
