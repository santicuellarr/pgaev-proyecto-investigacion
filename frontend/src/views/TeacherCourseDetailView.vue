<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CourseLeaderboard from '@/components/CourseLeaderboard.vue'
import ForumThread from '@/components/ForumThread.vue'
import TeacherAnalyticsPanel from '@/components/TeacherAnalyticsPanel.vue'
import { useGamificationStore } from '@/stores/gamification'
import { useTeacherStore } from '@/stores/teacher'

const route = useRoute()
const router = useRouter()
const teacher = useTeacherStore()
const gamification = useGamificationStore()

const tabs = [
  'Resumen',
  'Actividades',
  'Entregas',
  'Sesiones',
  'Estudiantes',
  'Clasificación',
  'Recompensas',
  'Insignias',
  'Reportes',
]

const activeTab = ref('Resumen')
const showActivityModal = ref(false)
const editingActivityId = ref(null)
const submissionFilter = ref('pending')
const selectedSubmissionId = ref(null)
const gradeForm = reactive({
  score: 0,
  teacher_feedback: '',
})
const regeneratingAi = ref(false)

const showRewardModal = ref(false)
const editingRewardId = ref(null)
const rewardForm = reactive({
  name: '',
  cost_points: 50,
  stock: 10,
  needs_approval: false,
  available_from: '',
  available_to: '',
})

const showBadgeModal = ref(false)
const editingBadgeId = ref(null)
const awardUserId = ref('')
const showSessionModal = ref(false)
const editingSessionId = ref(null)
const attendanceSessionId = ref(null)
const attendanceStatus = reactive({})
const showForumModal = ref(false)
const forumActivity = ref(null)
const sessionForm = reactive({
  title: '',
  starts_at: '',
  duration_min: 60,
  meeting_url: '',
})
const badgeForm = reactive({
  name: '',
  description: '',
  icon: '🏅',
  rule_type: 'first_submission',
  rule_value: 1,
})

const ruleLabels = {
  first_submission: 'Primer envío',
  submissions_count: 'Cantidad de envíos',
  points_total: 'Puntos totales',
  level_reached: 'Nivel alcanzado',
  manual: 'Manual',
}
const formErrors = reactive({
  title: '',
  type: '',
  base_points: '',
  max_attempts: '',
})

const activityForm = reactive({
  title: '',
  type: 'tarea',
  base_points: 50,
  max_attempts: 1,
  due_at: '',
  is_published: false,
})

const courseId = computed(() => Number(route.params.id))

const statusLabels = {
  draft: 'Borrador',
  published: 'Publicado',
  archived: 'Archivado',
}

onMounted(() => loadCourse())

watch(courseId, () => loadCourse())

watch(activeTab, async (tab) => {
  if (tab === 'Clasificación' && courseId.value) {
    await gamification.fetchLeaderboard(courseId.value)
  }

  if (tab === 'Entregas' && courseId.value) {
    try {
      await teacher.fetchCourseSubmissions()
    } catch {
      // teacher.error
    }
  }

  if (tab === 'Reportes' && courseId.value) {
    try {
      await teacher.fetchAnalytics(courseId.value)
    } catch {
      // teacher.error
    }
  }
})

const filteredSubmissions = computed(() => {
  if (submissionFilter.value === 'all') {
    return teacher.submissions
  }

  const status = submissionFilter.value === 'pending' ? 'submitted' : 'graded'
  return teacher.submissions.filter((item) => item.status === status)
})

const selectedSubmission = computed(() => {
  return teacher.submissions.find((item) => item.id === selectedSubmissionId.value) || null
})

async function loadCourse() {
  teacher.clearMessages()
  await teacher.fetchCourse(courseId.value)
}

function openCreateActivity() {
  teacher.clearMessages()
  editingActivityId.value = null
  activityForm.title = ''
  activityForm.type = 'tarea'
  activityForm.base_points = 50
  activityForm.max_attempts = 1
  activityForm.due_at = ''
  activityForm.is_published = false
  clearFormErrors()
  showActivityModal.value = true
}

function openEditActivity(activity) {
  teacher.clearMessages()
  editingActivityId.value = activity.id
  activityForm.title = activity.title
  activityForm.type = activity.type
  activityForm.base_points = activity.base_points
  activityForm.max_attempts = activity.max_attempts
  activityForm.due_at = toDatetimeLocal(activity.due_at)
  activityForm.is_published = Boolean(activity.is_published)
  clearFormErrors()
  showActivityModal.value = true
}

function toDatetimeLocal(value) {
  if (!value) {
    return ''
  }

  const date = new Date(value)
  const pad = (part) => String(part).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function clearFormErrors() {
  formErrors.title = ''
  formErrors.type = ''
  formErrors.base_points = ''
  formErrors.max_attempts = ''
}

function validateActivityForm() {
  clearFormErrors()
  let valid = true

  if (!activityForm.title.trim()) {
    formErrors.title = 'El título es obligatorio.'
    valid = false
  }

  if (!activityForm.type) {
    formErrors.type = 'Selecciona un tipo.'
    valid = false
  }

  if (!activityForm.base_points || Number(activityForm.base_points) < 1) {
    formErrors.base_points = 'Los puntos deben ser al menos 1.'
    valid = false
  }

  if (!activityForm.max_attempts || Number(activityForm.max_attempts) < 1) {
    formErrors.max_attempts = 'Debe haber al menos 1 intento.'
    valid = false
  }

  return valid
}

function activityPayload() {
  return {
    title: activityForm.title.trim(),
    type: activityForm.type,
    base_points: Number(activityForm.base_points),
    max_attempts: Number(activityForm.max_attempts),
    due_at: activityForm.due_at ? new Date(activityForm.due_at).toISOString() : null,
    is_published: activityForm.is_published,
  }
}

async function handleActivitySubmit() {
  if (!validateActivityForm()) {
    return
  }

  try {
    if (editingActivityId.value) {
      await teacher.updateActivity(editingActivityId.value, activityPayload())
    } else {
      await teacher.createActivity(courseId.value, activityPayload())
    }
    showActivityModal.value = false
    await teacher.fetchCourse(courseId.value)
  } catch {
    // teacher.error
  }
}

async function handlePublish(activity, published) {
  try {
    await teacher.publishActivity(activity.id, published)
    await teacher.fetchCourse(courseId.value)
  } catch {
    // teacher.error
  }
}

async function handleDelete(activity) {
  if (!window.confirm(`¿Eliminar "${activity.title}"? No se puede si ya tiene envíos.`)) {
    return
  }

  try {
    await teacher.deleteActivity(activity.id)
    await teacher.fetchCourse(courseId.value)
  } catch {
    // teacher.error
  }
}

async function handleStatusChange(event) {
  await teacher.updateCourseStatus(courseId.value, event.target.value)
  await teacher.fetchCourse(courseId.value)
}

async function copyInvite() {
  if (teacher.course?.invite_code) {
    await navigator.clipboard.writeText(teacher.course.invite_code)
  }
}

function formatDate(value) {
  if (!value) {
    return '—'
  }

  return new Date(value).toLocaleString('es-CO')
}

function submissionText(submission) {
  return submission?.content?.text || 'Sin contenido'
}

function statusLabel(status) {
  return status === 'graded' ? 'Calificada' : 'Pendiente'
}

function openSubmission(submission) {
  selectedSubmissionId.value = submission.id
  gradeForm.score = Number(submission.score || 0)
  gradeForm.teacher_feedback = submission.teacher_feedback || ''
}

async function handleGrade() {
  if (!selectedSubmission.value) {
    return
  }

  try {
    await teacher.gradeSubmission(selectedSubmission.value.id, {
      score: Number(gradeForm.score),
      teacher_feedback: gradeForm.teacher_feedback,
    })
    await teacher.fetchCourse(courseId.value)
    await teacher.fetchCourseSubmissions()
    const updated = teacher.submissions.find((item) => item.id === selectedSubmissionId.value)
    if (updated) {
      openSubmission(updated)
    }
  } catch {
    // teacher.error
  }
}

async function handleRegenerateAi() {
  if (!selectedSubmission.value) {
    return
  }

  regeneratingAi.value = true
  try {
    const updated = await teacher.regenerateAiFeedback(selectedSubmission.value.id)
    gradeForm.teacher_feedback = updated.teacher_feedback || gradeForm.teacher_feedback
  } catch {
    // teacher.error
  } finally {
    regeneratingAi.value = false
  }
}

function openCreateReward() {
  teacher.clearMessages()
  editingRewardId.value = null
  rewardForm.name = ''
  rewardForm.cost_points = 50
  rewardForm.stock = 10
  rewardForm.needs_approval = false
  rewardForm.available_from = ''
  rewardForm.available_to = ''
  showRewardModal.value = true
}

function openEditReward(reward) {
  teacher.clearMessages()
  editingRewardId.value = reward.id
  rewardForm.name = reward.name
  rewardForm.cost_points = reward.cost_points
  rewardForm.stock = reward.stock
  rewardForm.needs_approval = Boolean(reward.needs_approval)
  rewardForm.available_from = toDatetimeLocal(reward.available_from)
  rewardForm.available_to = toDatetimeLocal(reward.available_to)
  showRewardModal.value = true
}

function rewardPayload() {
  return {
    name: rewardForm.name.trim(),
    cost_points: Number(rewardForm.cost_points),
    stock: Number(rewardForm.stock),
    needs_approval: Boolean(rewardForm.needs_approval),
    available_from: rewardForm.available_from ? new Date(rewardForm.available_from).toISOString() : null,
    available_to: rewardForm.available_to ? new Date(rewardForm.available_to).toISOString() : null,
  }
}

async function handleRewardSubmit() {
  try {
    if (editingRewardId.value) {
      await teacher.updateReward(editingRewardId.value, rewardPayload())
    } else {
      await teacher.createReward(courseId.value, rewardPayload())
    }
    showRewardModal.value = false
  } catch {
    // teacher.error
  }
}

async function handleDeleteReward(reward) {
  if (!window.confirm(`¿Eliminar la recompensa "${reward.name}"? No se puede si ya tiene canjes.`)) {
    return
  }

  try {
    await teacher.deleteReward(reward.id)
  } catch {
    // teacher.error
  }
}

function openCreateBadge() {
  teacher.clearMessages()
  editingBadgeId.value = null
  badgeForm.name = ''
  badgeForm.description = ''
  badgeForm.icon = '🏅'
  badgeForm.rule_type = 'first_submission'
  badgeForm.rule_value = 1
  showBadgeModal.value = true
}

function openEditBadge(badge) {
  if (!badge.course_id) {
    return
  }

  teacher.clearMessages()
  editingBadgeId.value = badge.id
  badgeForm.name = badge.name
  badgeForm.description = badge.description || ''
  badgeForm.icon = badge.icon || '🏅'
  badgeForm.rule_type = badge.rule_type
  badgeForm.rule_value = badge.rule_value || 1
  showBadgeModal.value = true
}

function badgePayload() {
  return {
    name: badgeForm.name.trim(),
    description: badgeForm.description.trim() || null,
    icon: badgeForm.icon.trim() || null,
    rule_type: badgeForm.rule_type,
    rule_value: badgeForm.rule_type === 'manual' || badgeForm.rule_type === 'first_submission'
      ? null
      : Number(badgeForm.rule_value),
  }
}

async function handleBadgeSubmit() {
  try {
    if (editingBadgeId.value) {
      await teacher.updateBadge(courseId.value, editingBadgeId.value, badgePayload())
    } else {
      await teacher.createBadge(courseId.value, badgePayload())
    }
    showBadgeModal.value = false
  } catch {
    // teacher.error
  }
}

async function handleDeleteBadge(badge) {
  if (!badge.course_id) {
    return
  }

  if (!window.confirm(`¿Eliminar la insignia "${badge.name}"?`)) {
    return
  }

  try {
    await teacher.deleteBadge(courseId.value, badge.id)
  } catch {
    // teacher.error
  }
}

async function handleAwardBadge(badge) {
  if (!awardUserId.value) {
    return
  }

  try {
    await teacher.awardBadge(badge.id, Number(awardUserId.value))
  } catch {
    // teacher.error
  }
}

const attendanceSession = computed(() => {
  return teacher.sessions.find((item) => item.id === attendanceSessionId.value) || null
})

function openCreateSession() {
  teacher.clearMessages()
  editingSessionId.value = null
  sessionForm.title = ''
  sessionForm.starts_at = ''
  sessionForm.duration_min = 60
  sessionForm.meeting_url = ''
  showSessionModal.value = true
}

function openEditSession(session) {
  teacher.clearMessages()
  editingSessionId.value = session.id
  sessionForm.title = session.title
  sessionForm.starts_at = toDatetimeLocal(session.starts_at)
  sessionForm.duration_min = session.duration_min
  sessionForm.meeting_url = session.meeting_url || ''
  showSessionModal.value = true
}

function sessionPayload() {
  return {
    title: sessionForm.title.trim(),
    starts_at: sessionForm.starts_at ? new Date(sessionForm.starts_at).toISOString() : null,
    duration_min: Number(sessionForm.duration_min),
    meeting_url: sessionForm.meeting_url.trim() || null,
  }
}

async function handleSessionSubmit() {
  try {
    if (editingSessionId.value) {
      await teacher.updateSession(editingSessionId.value, sessionPayload())
    } else {
      await teacher.createSession(courseId.value, sessionPayload())
    }
    showSessionModal.value = false
  } catch {
    // teacher.error
  }
}

async function handleDeleteSession(session) {
  if (!window.confirm(`¿Eliminar la sesión "${session.title}"?`)) {
    return
  }

  try {
    await teacher.deleteSession(session.id)
    if (attendanceSessionId.value === session.id) {
      attendanceSessionId.value = null
    }
  } catch {
    // teacher.error
  }
}

function openAttendance(session) {
  attendanceSessionId.value = session.id
  teacher.enrollments.forEach((enrollment) => {
    const existing = session.attendances?.find((row) => row.user_id === enrollment.user_id)
    attendanceStatus[enrollment.user_id] = existing?.status || (existing ? existing.status : 'absent')
  })
}

async function handleSaveAttendance() {
  if (!attendanceSession.value) {
    return
  }

  const attendances = teacher.enrollments.map((enrollment) => ({
    user_id: enrollment.user_id,
    status: attendanceStatus[enrollment.user_id] || 'absent',
  }))

  try {
    await teacher.saveAttendance(attendanceSession.value.id, attendances)
  } catch {
    // teacher.error
  }
}

async function openForum(activity) {
  forumActivity.value = activity
  showForumModal.value = true
  teacher.clearMessages()
  try {
    await teacher.fetchForumPosts(activity.id)
  } catch {
    // teacher.error
  }
}
</script>

<template>
  <div v-if="teacher.loading && !teacher.course" class="text-slate-500">Cargando curso...</div>

  <div v-else-if="teacher.course" class="space-y-6">
    <button type="button" class="text-sm text-indigo-600 font-medium" @click="router.push({ name: 'teacher-courses' })">
      ← Mis cursos
    </button>

    <section class="rounded-2xl border border-slate-200 bg-white p-6">
      <div class="h-2 -mt-6 -mx-6 mb-4 rounded-t-2xl" :style="{ backgroundColor: teacher.course.color || '#4f46e5' }" />
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-slate-900">{{ teacher.course.title }}</h2>
          <p class="mt-1 text-sm text-slate-600">{{ teacher.course.description || 'Sin descripción' }}</p>
          <p class="mt-3 font-mono tracking-widest text-indigo-800">
            {{ teacher.course.invite_code }}
            <button type="button" class="ml-2 text-xs font-semibold text-indigo-600" @click="copyInvite">Copiar</button>
          </p>
        </div>
        <select
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
          :value="teacher.course.status"
          @change="handleStatusChange"
        >
          <option value="draft">Borrador</option>
          <option value="published">Publicado</option>
          <option value="archived">Archivado</option>
        </select>
      </div>
      <p class="mt-2 text-xs text-slate-500">Estado: {{ statusLabels[teacher.course.status] }}</p>
    </section>

    <div v-if="teacher.success" class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
      {{ teacher.success }}
    </div>
    <div v-if="teacher.error" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ teacher.error }}
    </div>
    <div
      v-if="teacher.toastMessage"
      class="fixed bottom-6 right-6 z-50 rounded-xl bg-slate-900 text-white px-4 py-3 text-sm font-semibold shadow-lg"
    >
      {{ teacher.toastMessage }}
    </div>

    <div class="flex gap-2 overflow-x-auto pb-1">
      <button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium"
        :class="activeTab === tab ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600'"
        @click="activeTab = tab"
      >
        {{ tab }}
      </button>
    </div>

    <section v-if="activeTab === 'Resumen'" class="grid gap-4 sm:grid-cols-3">
      <article class="rounded-2xl border bg-white p-5">
        <p class="text-sm text-slate-500">Inscritos</p>
        <p class="text-3xl font-bold">{{ teacher.counts.enrolled }}</p>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <p class="text-sm text-slate-500">Actividades</p>
        <p class="text-3xl font-bold">{{ teacher.counts.activities }}</p>
      </article>
      <article class="rounded-2xl border bg-white p-5">
        <p class="text-sm text-slate-500">Entregas pendientes</p>
        <p class="text-3xl font-bold text-amber-700">{{ teacher.counts.pending_submissions }}</p>
      </article>
    </section>

    <section v-else-if="activeTab === 'Actividades'" class="space-y-4">
      <button type="button" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold" @click="openCreateActivity">
        + Nueva actividad
      </button>
      <article v-for="activity in teacher.activities" :key="activity.id" class="rounded-2xl border bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs uppercase text-indigo-600 font-semibold">{{ activity.type }}</p>
            <h3 class="text-lg font-semibold">{{ activity.title }}</h3>
            <p class="text-sm text-slate-500">{{ activity.base_points }} pts · {{ activity.max_attempts }} intentos</p>
          </div>
          <span
            class="rounded-full px-2.5 py-1 text-xs font-medium"
            :class="activity.is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800'"
          >
            {{ activity.is_published ? 'Publicada' : 'Borrador' }}
          </span>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="openEditActivity(activity)">Editar</button>
          <button
            v-if="!activity.is_published"
            type="button"
            class="rounded-lg bg-indigo-600 text-white px-3 py-1.5 text-sm"
            @click="handlePublish(activity, true)"
          >
            Publicar
          </button>
          <button
            v-else
            type="button"
            class="rounded-lg border px-3 py-1.5 text-sm"
            @click="handlePublish(activity, false)"
          >
            Despublicar
          </button>
          <button type="button" class="rounded-lg border border-red-200 text-red-700 px-3 py-1.5 text-sm" @click="handleDelete(activity)">
            Eliminar
          </button>
          <button
            v-if="activity.type === 'foro'"
            type="button"
            class="rounded-lg border px-3 py-1.5 text-sm"
            @click="openForum(activity)"
          >
            Ver foro
          </button>
        </div>
      </article>
      <p v-if="!teacher.activities.length" class="text-sm text-slate-500">Todavía no hay actividades en este curso.</p>
    </section>

    <section v-else-if="activeTab === 'Entregas'" class="space-y-4">
      <div class="flex flex-wrap gap-2">
        <button
          v-for="option in [
            { id: 'pending', label: 'Pendiente' },
            { id: 'graded', label: 'Calificada' },
            { id: 'all', label: 'Todas' },
          ]"
          :key="option.id"
          type="button"
          class="rounded-full px-3 py-1.5 text-sm font-medium"
          :class="submissionFilter === option.id ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600'"
          @click="submissionFilter = option.id"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="overflow-x-auto rounded-2xl border bg-white">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
              <tr>
                <th class="px-4 py-3 font-medium">Estudiante</th>
                <th class="px-4 py-3 font-medium">Actividad</th>
                <th class="px-4 py-3 font-medium">Intento</th>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium">Nota</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="submission in filteredSubmissions"
                :key="submission.id"
                class="border-t border-slate-100 cursor-pointer hover:bg-indigo-50"
                :class="selectedSubmissionId === submission.id ? 'bg-indigo-50' : ''"
                @click="openSubmission(submission)"
              >
                <td class="px-4 py-3">{{ submission.user?.name }}</td>
                <td class="px-4 py-3">{{ submission.activity?.title }}</td>
                <td class="px-4 py-3">{{ submission.attempt_number }}</td>
                <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(submission.submitted_at) }}</td>
                <td class="px-4 py-3">{{ statusLabel(submission.status) }}</td>
                <td class="px-4 py-3">{{ submission.score ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
          <p v-if="!filteredSubmissions.length" class="px-4 py-6 text-sm text-slate-500">No hay entregas con este filtro.</p>
        </div>

        <aside v-if="selectedSubmission" class="rounded-2xl border bg-white p-5 space-y-4">
          <div>
            <p class="text-xs uppercase text-indigo-600 font-semibold">{{ selectedSubmission.activity?.title }}</p>
            <h3 class="text-lg font-semibold">{{ selectedSubmission.user?.name }}</h3>
            <p class="text-xs text-slate-500">Intento {{ selectedSubmission.attempt_number }} · {{ formatDate(selectedSubmission.submitted_at) }}</p>
          </div>
          <div>
            <p class="text-sm font-medium text-slate-700 mb-1">Contenido</p>
            <p class="rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-wrap">{{ submissionText(selectedSubmission) }}</p>
          </div>
          <div>
            <div class="flex items-center justify-between gap-2 mb-1">
              <p class="text-sm font-medium text-slate-700">Retroalimentación IA</p>
              <span class="rounded-full bg-violet-100 text-violet-800 px-2 py-0.5 text-[11px] font-semibold">sugerida por IA</span>
            </div>
            <p class="rounded-lg bg-violet-50 p-3 text-sm whitespace-pre-wrap">{{ selectedSubmission.ai_feedback || 'Aún no hay sugerencia de IA.' }}</p>
            <button
              type="button"
              class="mt-2 text-xs font-semibold text-indigo-600"
              :disabled="regeneratingAi"
              @click="handleRegenerateAi"
            >
              {{ regeneratingAi ? 'Regenerando...' : 'Regenerar sugerencia' }}
            </button>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Nota (0-100)</label>
            <input v-model.number="gradeForm.score" type="number" min="0" max="100" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Comentario del docente</label>
            <textarea v-model="gradeForm.teacher_feedback" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
          </div>
          <button
            type="button"
            class="w-full rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-50"
            :disabled="teacher.saving"
            @click="handleGrade"
          >
            Calificar
          </button>
        </aside>
        <aside v-else class="rounded-2xl border border-dashed bg-white p-5 text-sm text-slate-500">
          Selecciona una entrega para ver el contenido y calificar.
        </aside>
      </div>
    </section>

    <section v-else-if="activeTab === 'Sesiones'" class="space-y-4">
      <button type="button" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold" @click="openCreateSession">
        + Nueva sesión
      </button>
      <article v-for="session in teacher.sessions" :key="session.id" class="rounded-2xl border bg-white p-5 space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="font-semibold">{{ session.title }}</h3>
            <p class="text-sm text-slate-500">{{ formatDate(session.starts_at) }} · {{ session.duration_min }} min</p>
            <a v-if="session.meeting_url" :href="session.meeting_url" class="text-xs text-indigo-600" target="_blank" rel="noreferrer">
              {{ session.meeting_url }}
            </a>
          </div>
          <div class="flex gap-2">
            <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="openAttendance(session)">Asistencia</button>
            <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="openEditSession(session)">Editar</button>
            <button type="button" class="rounded-lg border border-red-200 text-red-700 px-3 py-1.5 text-sm" @click="handleDeleteSession(session)">Eliminar</button>
          </div>
        </div>
        <div v-if="attendanceSessionId === session.id" class="border-t border-slate-100 pt-3 space-y-2">
          <p class="text-sm font-medium">Toma de asistencia</p>
          <label
            v-for="enrollment in teacher.enrollments"
            :key="enrollment.user_id"
            class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm"
          >
            <span>{{ enrollment.user?.name }}</span>
            <span class="flex items-center gap-2">
              <input
                type="checkbox"
                :checked="attendanceStatus[enrollment.user_id] === 'present' || attendanceStatus[enrollment.user_id] === 'late'"
                @change="attendanceStatus[enrollment.user_id] = $event.target.checked ? 'present' : 'absent'"
              />
              <select v-model="attendanceStatus[enrollment.user_id]" class="rounded border border-slate-300 px-2 py-1 text-xs">
                <option value="present">Presente</option>
                <option value="late">Tarde</option>
                <option value="absent">Ausente</option>
              </select>
            </span>
          </label>
          <button
            type="button"
            class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-50"
            :disabled="teacher.saving"
            @click="handleSaveAttendance"
          >
            Guardar asistencia
          </button>
        </div>
      </article>
      <p v-if="!teacher.sessions.length" class="text-sm text-slate-500">Aún no hay encuentros sincrónicos en este curso.</p>
    </section>

    <section v-else-if="activeTab === 'Estudiantes'" class="space-y-3">
      <article v-for="enrollment in teacher.enrollments" :key="enrollment.id" class="rounded-2xl border bg-white p-5 flex justify-between">
        <div>
          <p class="font-semibold">{{ enrollment.user?.name }}</p>
          <p class="text-sm text-slate-500">{{ enrollment.user?.email }}</p>
        </div>
        <p class="text-xs text-slate-500">{{ enrollment.status }}</p>
      </article>
    </section>

    <section v-else-if="activeTab === 'Clasificación'">
      <div v-if="gamification.loading" class="text-sm text-slate-500">Cargando clasificación...</div>
      <CourseLeaderboard v-else :leaderboard="gamification.leaderboard" />
    </section>

    <section v-else-if="activeTab === 'Recompensas'" class="space-y-4">
      <button type="button" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold" @click="openCreateReward">
        + Nueva recompensa
      </button>
      <article v-for="reward in teacher.rewards" :key="reward.id" class="rounded-2xl border bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="font-semibold">{{ reward.name }}</p>
            <p class="text-sm text-slate-500">{{ reward.cost_points }} pts · stock {{ reward.stock }}</p>
            <p class="text-xs text-slate-500 mt-1">
              Vigencia:
              {{ reward.available_from ? formatDate(reward.available_from) : 'inmediata' }}
              →
              {{ reward.available_to ? formatDate(reward.available_to) : 'sin límite' }}
            </p>
            <p class="mt-2 text-xs font-medium" :class="reward.needs_approval ? 'text-amber-700' : 'text-emerald-700'">
              {{ reward.needs_approval ? 'Requiere aprobación' : 'Canje automático' }}
            </p>
          </div>
          <div class="flex gap-2">
            <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="openEditReward(reward)">Editar</button>
            <button type="button" class="rounded-lg border border-red-200 text-red-700 px-3 py-1.5 text-sm" @click="handleDeleteReward(reward)">Eliminar</button>
          </div>
        </div>
      </article>
      <p v-if="!teacher.rewards.length" class="text-sm text-slate-500">No hay recompensas en este curso.</p>
    </section>

    <section v-else-if="activeTab === 'Insignias'" class="space-y-4">
      <button type="button" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold" @click="openCreateBadge">
        + Nueva insignia
      </button>
      <div class="grid gap-4 sm:grid-cols-2">
        <article v-for="badge in teacher.badges" :key="badge.id" class="rounded-2xl border bg-white p-5 space-y-3">
          <div class="flex items-start gap-3">
            <span class="text-3xl">{{ badge.icon || '🏅' }}</span>
            <div>
              <p class="font-semibold">{{ badge.name }}</p>
              <p class="text-sm text-slate-500">{{ badge.description || 'Sin descripción' }}</p>
              <p class="mt-1 text-xs text-indigo-700 font-medium">
                {{ ruleLabels[badge.rule_type] || badge.rule_type }}
                <span v-if="badge.rule_value">· {{ badge.rule_value }}</span>
                <span v-if="!badge.course_id"> · global</span>
              </p>
            </div>
          </div>
          <div v-if="badge.course_id" class="flex flex-wrap gap-2">
            <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="openEditBadge(badge)">Editar regla</button>
            <button type="button" class="rounded-lg border border-red-200 text-red-700 px-3 py-1.5 text-sm" @click="handleDeleteBadge(badge)">Eliminar</button>
          </div>
          <div v-if="badge.rule_type === 'manual' && badge.course_id" class="flex gap-2">
            <select v-model="awardUserId" class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
              <option value="">Otorgar a...</option>
              <option v-for="enrollment in teacher.enrollments" :key="enrollment.user_id" :value="enrollment.user_id">
                {{ enrollment.user?.name }}
              </option>
            </select>
            <button type="button" class="rounded-lg bg-indigo-600 text-white px-3 py-1.5 text-sm" @click="handleAwardBadge(badge)">Otorgar</button>
          </div>
        </article>
      </div>
      <p v-if="!teacher.badges.length" class="text-sm text-slate-500">Aún no hay insignias definidas.</p>
    </section>

    <section v-else-if="activeTab === 'Reportes'">
      <TeacherAnalyticsPanel
        v-if="teacher.analytics"
        :analytics="teacher.analytics"
        :course-id="courseId"
        show-csv
        @export="(type) => teacher.exportCourseCsv(courseId, type)"
      />
      <p v-else class="text-sm text-slate-500">Cargando analíticas...</p>
    </section>
  </div>

  <div
    v-if="showActivityModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
    @click.self="showActivityModal = false"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
      <h3 class="text-lg font-semibold mb-4">{{ editingActivityId ? 'Editar actividad' : 'Nueva actividad' }}</h3>
      <form class="space-y-4" @submit.prevent="handleActivitySubmit">
        <div>
          <label class="block text-sm font-medium mb-1">Título</label>
          <input v-model="activityForm.title" class="w-full rounded-lg border px-3 py-2" :class="formErrors.title ? 'border-red-400' : 'border-slate-300'" />
          <p v-if="formErrors.title" class="mt-1 text-xs text-red-600">{{ formErrors.title }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tipo</label>
          <select v-model="activityForm.type" class="w-full rounded-lg border px-3 py-2" :class="formErrors.type ? 'border-red-400' : 'border-slate-300'">
            <option value="quiz">Quiz</option>
            <option value="tarea">Tarea</option>
            <option value="foro">Foro</option>
            <option value="proyecto">Proyecto</option>
            <option value="evaluacion">Evaluación</option>
          </select>
          <p v-if="formErrors.type" class="mt-1 text-xs text-red-600">{{ formErrors.type }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Puntos</label>
          <input v-model.number="activityForm.base_points" type="number" min="1" class="w-full rounded-lg border px-3 py-2" :class="formErrors.base_points ? 'border-red-400' : 'border-slate-300'" />
          <p v-if="formErrors.base_points" class="mt-1 text-xs text-red-600">{{ formErrors.base_points }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Intentos máximos</label>
          <input v-model.number="activityForm.max_attempts" type="number" min="1" class="w-full rounded-lg border px-3 py-2" :class="formErrors.max_attempts ? 'border-red-400' : 'border-slate-300'" />
          <p v-if="formErrors.max_attempts" class="mt-1 text-xs text-red-600">{{ formErrors.max_attempts }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Fecha límite</label>
          <input v-model="activityForm.due_at" type="datetime-local" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="activityForm.is_published" type="checkbox" />
          Publicada
        </label>
        <div v-if="teacher.error" class="text-sm text-red-700">{{ teacher.error }}</div>
        <div class="flex justify-end gap-3">
          <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showActivityModal = false">Cancelar</button>
          <button type="submit" :disabled="teacher.saving" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-50">
            Guardar
          </button>
        </div>
      </form>
    </div>
  </div>

  <div
    v-if="showRewardModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
    @click.self="showRewardModal = false"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
      <h3 class="text-lg font-semibold mb-4">{{ editingRewardId ? 'Editar recompensa' : 'Nueva recompensa' }}</h3>
      <form class="space-y-4" @submit.prevent="handleRewardSubmit">
        <div>
          <label class="block text-sm font-medium mb-1">Nombre</label>
          <input v-model="rewardForm.name" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Costo (pts)</label>
            <input v-model.number="rewardForm.cost_points" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Stock</label>
            <input v-model.number="rewardForm.stock" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Vigencia desde</label>
            <input v-model="rewardForm.available_from" type="datetime-local" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Vigencia hasta</label>
            <input v-model="rewardForm.available_to" type="datetime-local" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
          </div>
        </div>
        <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
          Requiere aprobación
          <input v-model="rewardForm.needs_approval" type="checkbox" class="h-4 w-4" />
        </label>
        <div class="flex justify-end gap-3">
          <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showRewardModal = false">Cancelar</button>
          <button type="submit" :disabled="teacher.saving" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-50">
            Guardar
          </button>
        </div>
      </form>
    </div>
  </div>

  <div
    v-if="showBadgeModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
    @click.self="showBadgeModal = false"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
      <h3 class="text-lg font-semibold mb-4">{{ editingBadgeId ? 'Editar insignia' : 'Nueva insignia' }}</h3>
      <form class="space-y-4" @submit.prevent="handleBadgeSubmit">
        <div>
          <label class="block text-sm font-medium mb-1">Nombre</label>
          <input v-model="badgeForm.name" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Descripción</label>
          <textarea v-model="badgeForm.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Ícono</label>
          <input v-model="badgeForm.icon" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Regla</label>
          <select v-model="badgeForm.rule_type" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <option value="first_submission">Primer envío</option>
            <option value="submissions_count">Cantidad de envíos</option>
            <option value="points_total">Puntos totales</option>
            <option value="level_reached">Nivel alcanzado</option>
            <option value="manual">Manual</option>
          </select>
        </div>
        <div v-if="badgeForm.rule_type !== 'manual' && badgeForm.rule_type !== 'first_submission'">
          <label class="block text-sm font-medium mb-1">Valor de la regla</label>
          <input v-model.number="badgeForm.rule_value" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showBadgeModal = false">Cancelar</button>
          <button type="submit" :disabled="teacher.saving" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-50">
            Guardar
          </button>
        </div>
      </form>
    </div>
  </div>

  <div
    v-if="showSessionModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
    @click.self="showSessionModal = false"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
      <h3 class="text-lg font-semibold mb-4">{{ editingSessionId ? 'Editar sesión' : 'Nueva sesión' }}</h3>
      <form class="space-y-4" @submit.prevent="handleSessionSubmit">
        <div>
          <label class="block text-sm font-medium mb-1">Título</label>
          <input v-model="sessionForm.title" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Inicio</label>
          <input v-model="sessionForm.starts_at" type="datetime-local" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Duración (min)</label>
          <input v-model.number="sessionForm.duration_min" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">URL de la reunión</label>
          <input v-model="sessionForm.meeting_url" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showSessionModal = false">Cancelar</button>
          <button type="submit" :disabled="teacher.saving" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-50">
            Guardar
          </button>
        </div>
      </form>
    </div>
  </div>

  <div
    v-if="showForumModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
    @click.self="showForumModal = false"
  >
    <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
      <div class="flex items-start justify-between gap-3 mb-4">
        <div>
          <h3 class="text-lg font-semibold">{{ forumActivity?.title }}</h3>
          <p class="text-xs text-slate-500">Moderación de solo lectura</p>
        </div>
        <button type="button" class="text-sm text-slate-500" @click="showForumModal = false">Cerrar</button>
      </div>
      <ForumThread :posts="teacher.forumPosts" />
    </div>
  </div>
</template>
