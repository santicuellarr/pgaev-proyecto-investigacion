<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import CourseLeaderboard from '@/components/CourseLeaderboard.vue'
import TeacherAnalyticsPanel from '@/components/TeacherAnalyticsPanel.vue'
import { useAuthStore } from '@/stores/auth'
import { useTeacherStore } from '@/stores/teacher'

const router = useRouter()
const auth = useAuthStore()
const teacher = useTeacherStore()

onMounted(async () => {
  await Promise.all([teacher.fetchCourses(), teacher.fetchOverview()])
})
</script>

<template>
  <div class="space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
      <h2 class="text-xl font-bold text-slate-900">
        Hola{{ auth.user ? `, ${auth.user.name}` : '' }}
      </h2>
      <p class="mt-2 text-sm text-slate-600">
        Indicadores de tus cursos: completitud, asistencia, foros, puntos y canjes.
      </p>
    </section>

    <p v-if="teacher.error" class="text-sm text-red-700">{{ teacher.error }}</p>

    <TeacherAnalyticsPanel v-if="teacher.overview" :analytics="teacher.overview" />

    <CourseLeaderboard
      v-if="teacher.overview?.leaderboard_top?.length"
      :leaderboard="teacher.overview.leaderboard_top"
      :max-rows="5"
    />

    <button
      type="button"
      class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-semibold"
      @click="router.push({ name: 'teacher-courses' })"
    >
      Ir a Mis cursos
    </button>
  </div>
</template>
