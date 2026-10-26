<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import BarChart from '@/components/charts/BarChart.vue'
import DoughnutChart from '@/components/charts/DoughnutChart.vue'
import LineChart from '@/components/charts/LineChart.vue'

const props = defineProps({
  analytics: { type: Object, required: true },
  courseId: { type: Number, default: null },
  showCsv: { type: Boolean, default: false },
})

const emit = defineEmits(['export'])
const router = useRouter()

const kpis = computed(() => props.analytics?.kpis || {})

function pct(value) {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${value}%`
}

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
}

const completionChart = computed(() => ({
  labels: (props.analytics.completion_by_activity || []).map((row) => row.title),
  datasets: [{
    label: 'Completitud %',
    backgroundColor: '#4f46e5',
    data: (props.analytics.completion_by_activity || []).map((row) => row.completion_rate),
  }],
}))

const pointsChart = computed(() => ({
  labels: (props.analytics.points_distribution || []).map((row) => row.bucket),
  datasets: [{
    label: 'Estudiantes',
    backgroundColor: '#0ea5e9',
    data: (props.analytics.points_distribution || []).map((row) => row.count),
  }],
}))

const weeklyChart = computed(() => ({
  labels: (props.analytics.submissions_by_week || []).map((row) => row.week_start),
  datasets: [{
    label: 'Envíos',
    borderColor: '#059669',
    backgroundColor: 'rgba(5, 150, 105, 0.15)',
    fill: true,
    tension: 0.3,
    data: (props.analytics.submissions_by_week || []).map((row) => row.count),
  }],
}))

const redemptionsChart = computed(() => {
  const status = kpis.value.redemptions_by_status || { pending: 0, approved: 0, rejected: 0 }

  return {
    labels: ['Pendientes', 'Aprobados', 'Rechazados'],
    datasets: [{
      backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
      data: [status.pending, status.approved, status.rejected],
    }],
  }
})

const csvTypes = [
  { id: '', label: 'Estudiantes' },
  { id: 'completion', label: 'Completitud' },
  { id: 'distribution', label: 'Distribución' },
  { id: 'low_participation', label: 'Baja participación' },
  { id: 'attendance', label: 'Asistencia' },
  { id: 'forums', label: 'Foros' },
]

function historyCourseId(row) {
  return props.courseId || row.course_id
}

function openHistory(row) {
  const id = historyCourseId(row)
  if (!id) {
    return
  }

  router.push({
    name: 'teacher-student-history',
    params: { id, userId: row.user_id },
  })
}
</script>

<template>
  <div class="space-y-6">
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <article class="rounded-2xl border bg-white p-5">
        <p class="text-sm text-slate-500">{{ kpis.courses != null ? 'Cursos' : 'Inscritos' }}</p>
        <p class="mt-1 text-3xl font-bold">{{ kpis.courses != null ? kpis.courses : kpis.enrolled }}</p>
        <p v-if="kpis.courses != null" class="text-xs text-slate-500 mt-1">{{ kpis.enrolled }} inscritos</p>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <p class="text-sm text-slate-500">Promedio de notas</p>
        <p class="mt-1 text-3xl font-bold">{{ kpis.average_score ?? '—' }}</p>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <p class="text-sm text-slate-500">Puntos totales</p>
        <p class="mt-1 text-3xl font-bold">{{ kpis.total_points ?? 0 }}</p>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <p class="text-sm text-slate-500">Asistencia</p>
        <p class="mt-1 text-3xl font-bold">{{ pct(kpis.attendance_rate) }}</p>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <p class="text-sm text-slate-500">Participación en foros</p>
        <p class="mt-1 text-3xl font-bold">{{ pct(kpis.forum_participation_rate) }}</p>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <p class="text-sm text-slate-500">Canjes</p>
        <p class="mt-1 text-lg font-semibold text-slate-800">
          {{ kpis.redemptions_by_status?.pending || 0 }} pend. ·
          {{ kpis.redemptions_by_status?.approved || 0 }} apr. ·
          {{ kpis.redemptions_by_status?.rejected || 0 }} rec.
        </p>
      </article>
    </section>

    <div v-if="showCsv && courseId" class="flex flex-wrap gap-2">
      <button
        v-for="item in csvTypes"
        :key="item.id || 'students'"
        type="button"
        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700"
        @click="emit('export', item.id || null)"
      >
        CSV {{ item.label }}
      </button>
    </div>

    <section class="grid gap-4 lg:grid-cols-2">
      <article class="rounded-2xl border bg-white p-5">
        <h3 class="text-sm font-semibold mb-3">Completitud por actividad</h3>
        <div class="h-64">
          <BarChart :data="completionChart" :options="chartOptions" />
        </div>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <h3 class="text-sm font-semibold mb-3">Distribución de puntos</h3>
        <div class="h-64">
          <BarChart :data="pointsChart" :options="chartOptions" />
        </div>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <h3 class="text-sm font-semibold mb-3">Envíos por semana</h3>
        <div class="h-64">
          <LineChart :data="weeklyChart" :options="chartOptions" />
        </div>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <h3 class="text-sm font-semibold mb-3">Canjes por estado</h3>
        <div class="h-64">
          <DoughnutChart :data="redemptionsChart" :options="{ responsive: true, maintainAspectRatio: false }" />
        </div>
      </article>
    </section>

    <section class="rounded-2xl border bg-white overflow-hidden">
      <div class="px-5 py-4 border-b">
        <h3 class="font-semibold">Baja participación</h3>
      </div>
      <div v-if="!(analytics.low_participation || []).length" class="p-5 text-sm text-slate-500">
        No hay estudiantes en el umbral de baja participación.
      </div>
      <table v-else class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
          <tr>
            <th class="px-4 py-3 font-medium">Estudiante</th>
            <th class="px-4 py-3 font-medium">Completitud</th>
            <th class="px-4 py-3 font-medium">Motivo</th>
            <th class="px-4 py-3 font-medium"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in analytics.low_participation" :key="`${row.user_id}-${row.course_id || ''}`" class="border-t">
            <td class="px-4 py-3">
              <p class="font-medium">{{ row.name }}</p>
              <p class="text-xs text-slate-500">{{ row.email }}</p>
            </td>
            <td class="px-4 py-3">{{ pct(row.completion_rate) }}</td>
            <td class="px-4 py-3 text-slate-600">{{ (row.reasons || []).join(' · ') }}</td>
            <td class="px-4 py-3 text-right">
              <button type="button" class="text-indigo-600 text-xs font-semibold" @click="openHistory(row)">
                Ver historial
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>
