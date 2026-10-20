<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ForumThread from '@/components/ForumThread.vue'
import StudentAppHeader from '@/components/StudentAppHeader.vue'
import { useActivityStore } from '@/stores/activity'

const route = useRoute()
const activityStore = useActivityStore()

const content = ref('')
const idempotencyKey = ref(null)

const activityId = computed(() => Number(route.params.id))

const courseId = computed(() => activityStore.activity?.course_id ?? null)

const typeLabels = {
  quiz: 'Quiz',
  tarea: 'Tarea',
  foro: 'Foro',
  proyecto: 'Proyecto',
  evaluacion: 'Evaluación',
}

const typeBadgeClass = {
  quiz: 'bg-violet-100 text-violet-700',
  tarea: 'bg-blue-100 text-blue-700',
  foro: 'bg-amber-100 text-amber-700',
  proyecto: 'bg-emerald-100 text-emerald-700',
  evaluacion: 'bg-rose-100 text-rose-700',
}

const formattedDueDate = computed(() => {
  if (!activityStore.activity?.due_at) {
    return 'Sin fecha límite'
  }

  return new Intl.DateTimeFormat('es-ES', {
    dateStyle: 'long',
    timeStyle: 'short',
  }).format(new Date(activityStore.activity.due_at))
})

const typeLabel = computed(() => {
  const type = activityStore.activity?.type
  return typeLabels[type] || type
})

const badgeClass = computed(() => {
  const type = activityStore.activity?.type
  return typeBadgeClass[type] || 'bg-slate-100 text-slate-700'
})

const lastSubmission = computed(() => activityStore.lastSubmission)

const isForum = computed(() => activityStore.activity?.type === 'foro')

const isGraded = computed(() => lastSubmission.value?.status === 'graded')

const gradedScore = computed(() => {
  if (!isGraded.value || lastSubmission.value?.score == null) {
    return null
  }

  return Number(lastSubmission.value.score)
})

const pointsEarned = computed(() => {
  if (!isGraded.value || gradedScore.value == null || !activityStore.activity) {
    return null
  }

  const maxScore = 100

  return Math.round(Number(activityStore.activity.base_points) * gradedScore.value / maxScore)
})

const aiFeedback = computed(() => lastSubmission.value?.ai_feedback ?? null)

onMounted(async () => {
  await activityStore.fetchActivity(activityId.value)
  if (activityStore.activity?.type === 'foro') {
    await activityStore.fetchForumPosts(activityId.value)
  }
})

async function handleSubmit() {
  if (activityStore.submitting) {
    return
  }

  activityStore.clearMessages()

  if (!idempotencyKey.value) {
    idempotencyKey.value = crypto.randomUUID()
  }

  try {
    await activityStore.submitActivity(
      activityId.value,
      content.value.trim(),
      idempotencyKey.value,
    )
  } catch {
    // El error se muestra desde activityStore.error
  }
}

async function handleForumSubmit({ body, parentId }) {
  activityStore.clearMessages()
  try {
    await activityStore.createForumPost(activityId.value, body, parentId)
  } catch {
    // activityStore.error
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <StudentAppHeader
      title="Detalle de actividad"
      :course-id="courseId"
      show-back
    />

    <main class="max-w-4xl mx-auto px-4 py-8">
      <div v-if="activityStore.loading" class="text-slate-500">
        Cargando actividad...
      </div>

      <div
        v-else-if="activityStore.error && !activityStore.activity"
        class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
        role="alert"
      >
        {{ activityStore.error }}
      </div>

      <template v-else-if="activityStore.activity">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <span
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                :class="badgeClass"
              >
                {{ typeLabel }}
              </span>
              <h1 class="mt-3 text-2xl font-bold text-slate-900">
                {{ activityStore.activity.title }}
              </h1>
            </div>
            <div class="text-right">
              <p class="text-sm text-slate-500">Puntos asignables</p>
              <p class="text-2xl font-bold text-indigo-600">
                {{ activityStore.activity.base_points }}
              </p>
            </div>
          </div>

          <div class="mt-5 flex flex-wrap gap-4 text-sm">
            <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
              <p class="text-slate-500">Fecha límite</p>
              <p class="mt-1 font-medium text-slate-900">{{ formattedDueDate }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
              <p class="text-slate-500">Intentos máximos</p>
              <p class="mt-1 font-medium text-slate-900">
                {{ activityStore.activity.max_attempts }}
              </p>
            </div>
          </div>
        </article>

        <section v-if="isForum" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-900 mb-1">Foro del curso</h2>
          <p class="text-sm text-slate-500 mb-4">
            El primer aporte cuenta como participación y otorga los puntos de la actividad una sola vez.
          </p>
          <ForumThread
            :posts="activityStore.forumPosts"
            can-post
            :submitting="activityStore.submitting"
            :error="activityStore.error || ''"
            :success="activityStore.success || ''"
            @submit="handleForumSubmit"
          />
        </section>

        <section v-else class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-900 mb-1">Tu respuesta</h2>
          <p class="text-sm text-slate-500 mb-4">
            Escribe tu respuesta y envíala antes de la fecha límite.
          </p>

          <div
            v-if="activityStore.success"
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700"
            role="status"
          >
            {{ activityStore.success }}
          </div>

          <div
            v-if="activityStore.error"
            class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
            role="alert"
          >
            {{ activityStore.error }}
          </div>

          <form @submit.prevent="handleSubmit">
            <textarea
              v-model="content"
              rows="8"
              required
              :disabled="activityStore.submitting || Boolean(activityStore.success)"
              class="border border-slate-300 focus:ring-2 focus:ring-indigo-500 rounded-lg p-3 w-full text-slate-900 placeholder-slate-400 disabled:bg-slate-100 disabled:cursor-not-allowed"
              placeholder="Escribe aquí tu respuesta..."
            />

            <button
              type="submit"
              :disabled="activityStore.submitting || !content.trim() || Boolean(activityStore.success)"
              class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white px-5 py-2.5 text-sm font-semibold transition"
            >
              <svg
                v-if="activityStore.submitting"
                class="h-4 w-4 animate-spin"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                />
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>
              {{ activityStore.submitting ? 'Enviando...' : 'Enviar respuesta' }}
            </button>
          </form>

          <section
            v-if="isGraded"
            class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-4"
          >
            <h3 class="text-sm font-semibold text-emerald-900">Calificación</h3>
            <div class="flex flex-wrap gap-4 text-sm">
              <div>
                <p class="text-emerald-700">Nota</p>
                <p class="mt-0.5 text-xl font-bold text-emerald-950">
                  {{ gradedScore }} / 100
                </p>
              </div>
              <div>
                <p class="text-emerald-700">Puntos ganados</p>
                <p class="mt-0.5 text-xl font-bold text-emerald-950">
                  {{ pointsEarned }}
                </p>
              </div>
            </div>
            <div
              v-if="aiFeedback"
              class="rounded-xl border border-indigo-200 bg-indigo-50 p-4"
            >
              <span
                class="mb-3 inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"
              >
                Copiloto IA
              </span>
              <p class="whitespace-pre-wrap text-sm leading-relaxed text-indigo-900">
                {{ aiFeedback }}
              </p>
            </div>
          </section>

          <div
            v-else-if="aiFeedback"
            class="mt-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4"
          >
            <span
              class="mb-3 inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"
            >
              Copiloto IA
            </span>
            <p class="whitespace-pre-wrap text-sm leading-relaxed text-indigo-900">
              {{ aiFeedback }}
            </p>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>
