<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StudentAppHeader from '@/components/StudentAppHeader.vue'
import { useActivityStore } from '@/stores/activity'

const route = useRoute()
const router = useRouter()
const activityStore = useActivityStore()

const courseId = Number(route.params.courseId)

const typeLabels = {
  quiz: 'Quiz',
  tarea: 'Tarea',
  foro: 'Foro',
  proyecto: 'Proyecto',
  evaluacion: 'Evaluación',
}

onMounted(async () => {
  await activityStore.fetchCourseActivities(courseId)
})
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <StudentAppHeader
      title="Actividades del curso"
      :course-id="courseId"
      show-back
    />

    <main class="max-w-4xl mx-auto px-4 py-8">
      <div class="mb-6 flex justify-end">
        <button
          type="button"
          class="rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 px-4 py-2 text-sm font-semibold transition"
          @click="router.push({ name: 'course-leaderboard', params: { courseId } })"
        >
          Ver clasificación
        </button>
      </div>

      <div v-if="activityStore.loading" class="text-slate-500">
        Cargando actividades...
      </div>

      <div
        v-else-if="activityStore.error"
        class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
        role="alert"
      >
        {{ activityStore.error }}
      </div>

      <div
        v-else-if="!activityStore.activities.length"
        class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
      >
        <p class="text-slate-600">No hay actividades publicadas en este curso.</p>
      </div>

      <div v-else class="grid gap-4">
        <article
          v-for="activity in activityStore.activities"
          :key="activity.id"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-200 transition cursor-pointer"
          @click="router.push({ name: 'activity-detail', params: { id: activity.id } })"
        >
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                {{ typeLabels[activity.type] || activity.type }}
              </p>
              <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ activity.title }}</h2>
              <p class="mt-1 text-sm text-slate-500">
                {{ activity.base_points }} pts ·
                {{ activity.due_at
                  ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' }).format(new Date(activity.due_at))
                  : 'Sin fecha límite' }}
              </p>
            </div>
            <span class="text-indigo-600 text-sm font-medium">Abrir →</span>
          </div>
        </article>
      </div>
    </main>
  </div>
</template>
