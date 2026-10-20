<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import StudentAppHeader from '@/components/StudentAppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/course'
import { useGamificationStore } from '@/stores/gamification'

const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()
const gamification = useGamificationStore()

const inviteCode = ref('')
const selectedCourseId = ref(null)

const progress = computed(() => gamification.progress)
const badges = computed(() => progress.value?.badges?.available ?? [])

function formatDate(value) {
  if (!value) {
    return 'Sin fecha'
  }

  return new Intl.DateTimeFormat('es-ES', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(async () => {
  await courseStore.fetchCourses()
  selectedCourseId.value = courseStore.courses[0]?.id ?? null
})

watch(selectedCourseId, async (courseId) => {
  if (courseId) {
    try {
      await gamification.fetchProgress(courseId)
    } catch {
      // El error se muestra desde gamification.error
    }
  }
})

async function handleEnroll() {
  courseStore.clearMessages()

  try {
    await courseStore.enrollInCourse(inviteCode.value.trim())
    inviteCode.value = ''
    selectedCourseId.value = courseStore.courses[0]?.id ?? selectedCourseId.value
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
      :course-id="selectedCourseId"
    />

    <main class="max-w-6xl mx-auto px-4 py-8 space-y-8 overflow-x-auto">
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

      <section v-if="courseStore.courses.length" class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Tu progreso</h2>
            <p class="text-sm text-slate-500">Nivel, puntos, racha e insignias del curso seleccionado.</p>
          </div>
          <label class="text-sm text-slate-600">
            Curso
            <select
              v-model.number="selectedCourseId"
              class="ml-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
            >
              <option v-for="course in courseStore.courses" :key="course.id" :value="course.id">
                {{ course.title }}
              </option>
            </select>
          </label>
        </div>

        <p v-if="gamification.error" class="text-sm text-red-700">{{ gamification.error }}</p>

        <div v-if="progress" class="grid gap-4 lg:grid-cols-4">
          <article class="lg:col-span-2 rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-600 to-violet-600 p-6 text-white shadow-sm">
            <p class="text-sm text-indigo-100">Nivel actual</p>
            <h3 class="mt-1 text-3xl font-bold">
              {{ progress.current_level.name }}
              <span class="text-lg font-medium text-indigo-100">#{{ progress.current_level.level_number }}</span>
            </h3>
            <p v-if="progress.is_max_level" class="mt-3 text-sm text-indigo-100">Alcanzaste el nivel máximo de este curso.</p>
            <p v-else class="mt-3 text-sm text-indigo-100">
              Faltan {{ progress.points_to_next }} pts para {{ progress.next_level?.name }}
              ({{ progress.next_level?.points_required }} pts).
            </p>
            <div class="mt-4 h-3 overflow-hidden rounded-full bg-white/20">
              <div
                class="h-full rounded-full bg-amber-300 transition-all duration-500 motion-reduce:transition-none"
                :style="{ width: `${progress.progress_percent}%` }"
              />
            </div>
            <p class="mt-2 text-xs text-indigo-100">{{ progress.progress_percent }}% hacia el siguiente umbral</p>
          </article>

          <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Puntos disponibles</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ progress.available_points }}</p>
            <p class="mt-1 text-xs text-slate-500">Saldo actual del curso</p>
          </article>

          <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Clasificación</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">#{{ progress.rank }}</p>
            <p class="mt-3 text-sm text-orange-600 font-semibold">Racha {{ progress.streak_days }} días</p>
          </article>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-base font-semibold text-slate-900">Galería de insignias</h3>
          <div v-if="!badges.length" class="mt-3 text-sm text-slate-500">Aún no hay insignias en este curso.</div>
          <div v-else class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <article
              v-for="badge in badges"
              :key="badge.id"
              class="rounded-xl border p-4"
              :class="badge.earned ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-100 grayscale'"
            >
              <p class="text-2xl">{{ badge.icon || '🏅' }}</p>
              <h4 class="mt-2 font-semibold text-slate-900">{{ badge.name }}</h4>
              <p class="mt-1 text-xs text-slate-600">{{ badge.condition }}</p>
              <p class="mt-2 text-xs font-medium" :class="badge.earned ? 'text-amber-700' : 'text-slate-500'">
                {{ badge.earned ? 'Obtenida' : 'Bloqueada' }}
              </p>
            </article>
          </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
          <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Próximas actividades</h3>
            <ul v-if="progress?.upcoming_activities?.length" class="mt-4 space-y-3">
              <li
                v-for="activity in progress.upcoming_activities"
                :key="activity.id"
                class="flex items-start justify-between gap-3 rounded-xl border border-slate-100 px-3 py-2"
              >
                <div>
                  <p class="font-medium text-slate-900">{{ activity.title }}</p>
                  <p class="text-xs text-slate-500">{{ activity.type }} · {{ activity.base_points }} pts</p>
                </div>
                <p class="text-xs font-semibold text-indigo-700 whitespace-nowrap">{{ formatDate(activity.due_at) }}</p>
              </li>
            </ul>
            <p v-else class="mt-3 text-sm text-slate-500">No hay actividades con fecha límite próxima.</p>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Últimas calificaciones</h3>
            <ul v-if="progress?.recent_grades?.length" class="mt-4 space-y-3">
              <li
                v-for="grade in progress.recent_grades"
                :key="grade.id"
                class="rounded-xl border border-slate-100 px-3 py-3"
              >
                <div class="flex items-center justify-between gap-3">
                  <p class="font-medium text-slate-900">{{ grade.title }}</p>
                  <span class="text-sm font-bold text-indigo-700">{{ grade.score }}</span>
                </div>
                <p v-if="grade.ai_feedback" class="mt-2 text-sm text-slate-600">
                  <span class="font-semibold text-violet-700">IA:</span> {{ grade.ai_feedback }}
                </p>
                <p v-if="grade.teacher_feedback" class="mt-1 text-sm text-slate-600">
                  <span class="font-semibold text-indigo-700">Docente:</span> {{ grade.teacher_feedback }}
                </p>
              </li>
            </ul>
            <p v-else class="mt-3 text-sm text-slate-500">Aún no tienes calificaciones.</p>
          </section>
        </div>
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
