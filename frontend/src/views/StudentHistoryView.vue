<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import StudentAppHeader from '@/components/StudentAppHeader.vue'
import { useCourseStore } from '@/stores/course'
import { useGamificationStore } from '@/stores/gamification'

const courseStore = useCourseStore()
const gamification = useGamificationStore()

const courseId = ref('')
const type = ref('')
const from = ref('')
const to = ref('')
const page = ref(1)

const typeLabels = {
  point_log: 'Puntos',
  submission: 'Envío',
  badge: 'Insignia',
  redemption: 'Canje',
}

const typeStyles = {
  point_log: 'bg-sky-100 text-sky-800',
  submission: 'bg-indigo-100 text-indigo-800',
  badge: 'bg-violet-100 text-violet-800',
  redemption: 'bg-emerald-100 text-emerald-800',
}

const query = computed(() => {
  const params = { page: page.value }

  if (courseId.value) {
    params.course_id = courseId.value
  }

  if (type.value) {
    params.type = type.value
  }

  if (from.value) {
    params.from = from.value
  }

  if (to.value) {
    params.to = to.value
  }

  return params
})

async function loadHistory() {
  try {
    await gamification.fetchHistory(query.value)
  } catch {
    // El error se muestra desde gamification.error
  }
}

function applyFilters() {
  if (page.value === 1) {
    loadHistory()
    return
  }

  page.value = 1
}

function formatDate(value) {
  return new Intl.DateTimeFormat('es-ES', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(async () => {
  await courseStore.fetchCourses()
  await loadHistory()
})

watch(page, loadHistory)
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <StudentAppHeader title="Historial" subtitle="Línea de tiempo de tu progreso" show-back />

    <main class="max-w-3xl mx-auto px-4 py-8 space-y-6">
      <form class="rounded-2xl border border-slate-200 bg-white p-4 grid gap-3 sm:grid-cols-2" @submit.prevent="applyFilters">
        <label class="text-sm text-slate-600">
          Curso
          <select v-model="courseId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            <option value="">Todos</option>
            <option v-for="course in courseStore.courses" :key="course.id" :value="course.id">
              {{ course.title }}
            </option>
          </select>
        </label>
        <label class="text-sm text-slate-600">
          Tipo
          <select v-model="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            <option value="">Todos</option>
            <option value="point_log">Puntos</option>
            <option value="submission">Envíos</option>
            <option value="badge">Insignias</option>
            <option value="redemption">Canjes</option>
          </select>
        </label>
        <label class="text-sm text-slate-600">
          Desde
          <input v-model="from" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
        </label>
        <label class="text-sm text-slate-600">
          Hasta
          <input v-model="to" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
        </label>
        <div class="sm:col-span-2">
          <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">
            Filtrar
          </button>
        </div>
      </form>

      <p v-if="gamification.error" class="text-sm text-red-700">{{ gamification.error }}</p>

      <ol v-if="gamification.history.length" class="relative border-l border-indigo-200 ml-3 space-y-4">
        <li
          v-for="entry in gamification.history"
          :key="`${entry.type}-${entry.meta?.id}-${entry.occurred_at}`"
          class="ml-6"
        >
          <span class="absolute -left-2 mt-1.5 h-4 w-4 rounded-full border-2 border-white bg-indigo-500" />
          <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
              <p class="font-semibold text-slate-900">{{ entry.title }}</p>
              <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase" :class="typeStyles[entry.type]">
                {{ typeLabels[entry.type] || entry.type }}
              </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ formatDate(entry.occurred_at) }}</p>
            <p v-if="entry.delta != null" class="mt-2 text-sm font-medium text-slate-700">
              {{ entry.delta > 0 ? '+' : '' }}{{ entry.delta }} pts
            </p>
          </article>
        </li>
      </ol>

      <p v-else-if="!gamification.loading" class="text-sm text-slate-500">Sin eventos para los filtros seleccionados.</p>

      <div v-if="gamification.historyMeta.last_page > 1" class="flex items-center justify-between">
        <button
          type="button"
          class="text-sm font-medium text-indigo-600 disabled:text-slate-400"
          :disabled="page <= 1"
          @click="page -= 1"
        >
          Anterior
        </button>
        <p class="text-xs text-slate-500">Página {{ gamification.historyMeta.current_page }} de {{ gamification.historyMeta.last_page }}</p>
        <button
          type="button"
          class="text-sm font-medium text-indigo-600 disabled:text-slate-400"
          :disabled="page >= gamification.historyMeta.last_page"
          @click="page += 1"
        >
          Siguiente
        </button>
      </div>
    </main>
  </div>
</template>
