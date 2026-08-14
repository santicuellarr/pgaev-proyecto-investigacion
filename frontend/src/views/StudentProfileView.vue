<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import StudentAppHeader from '@/components/StudentAppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/course'
import { useRewardStore } from '@/stores/reward'

const auth = useAuthStore()
const courseStore = useCourseStore()
const rewardStore = useRewardStore()

const selectedCourseId = ref(null)

const selectedCourse = computed(() =>
  courseStore.courses.find((course) => course.id === selectedCourseId.value) ?? null,
)

const badgeMedals = {
  'Primer Envío': '🥇',
  default: '🏅',
}

function getMedalIcon(badgeName) {
  return badgeMedals[badgeName] || badgeMedals.default
}

function formatDate(date) {
  return new Intl.DateTimeFormat('es-ES', { dateStyle: 'long' }).format(new Date(date))
}

onMounted(async () => {
  await courseStore.fetchCourses()
  selectedCourseId.value = courseStore.courses[0]?.id ?? null
})

watch(selectedCourseId, async (courseId) => {
  if (courseId) {
    await rewardStore.fetchMyBadges(courseId)
  }
}, { immediate: true })
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <StudentAppHeader
      title="Mi Perfil"
      :subtitle="auth.user ? auth.user.name : ''"
      :course-id="selectedCourseId"
    />

    <main class="max-w-6xl mx-auto px-4 py-8 space-y-8">
      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Información personal</h2>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
          <div>
            <dt class="text-slate-500">Nombre</dt>
            <dd class="font-medium text-slate-900">{{ auth.user?.name }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Correo</dt>
            <dd class="font-medium text-slate-900">{{ auth.user?.email }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Rol</dt>
            <dd class="font-medium text-slate-900 capitalize">{{ auth.user?.role }}</dd>
          </div>
        </dl>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Mis Insignias</h2>
            <p class="text-sm text-slate-500 mt-1">Medallas obtenidas por tus logros en el curso.</p>
          </div>

          <select
            v-if="courseStore.courses.length"
            v-model="selectedCourseId"
            class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option
              v-for="course in courseStore.courses"
              :key="course.id"
              :value="course.id"
            >
              {{ course.title }}
            </option>
          </select>
        </div>

        <p v-if="selectedCourse" class="text-xs text-slate-500 mb-4">
          Curso: <span class="font-medium text-slate-700">{{ selectedCourse.title }}</span>
        </p>

        <div v-if="rewardStore.loading" class="text-slate-500">
          Cargando insignias...
        </div>

        <div
          v-else-if="rewardStore.error"
          class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
          role="alert"
        >
          {{ rewardStore.error }}
        </div>

        <div
          v-else-if="!rewardStore.badges.length"
          class="rounded-xl border border-dashed border-slate-300 p-10 text-center"
        >
          <p class="text-4xl mb-3">🏅</p>
          <p class="text-slate-600">Aún no has obtenido insignias en este curso.</p>
          <p class="text-sm text-slate-500 mt-1">Completa actividades para desbloquear medallas.</p>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="userBadge in rewardStore.badges"
            :key="userBadge.id"
            class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-yellow-50 p-5 text-center shadow-sm"
          >
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-amber-200 to-yellow-400 text-4xl shadow-inner ring-4 ring-amber-100">
              {{ getMedalIcon(userBadge.badge?.name) }}
            </div>
            <h3 class="mt-4 text-base font-semibold text-slate-900">
              {{ userBadge.badge?.name }}
            </h3>
            <p class="mt-1 text-sm text-slate-600 line-clamp-2">
              {{ userBadge.badge?.description || 'Insignia de logro' }}
            </p>
            <p class="mt-3 text-xs font-medium text-amber-800">
              Obtenida el {{ formatDate(userBadge.awarded_at) }}
            </p>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>
