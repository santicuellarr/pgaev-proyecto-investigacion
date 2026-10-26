<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import CourseLeaderboard from '@/components/CourseLeaderboard.vue'
import StudentAppHeader from '@/components/StudentAppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useGamificationStore } from '@/stores/gamification'

const route = useRoute()
const auth = useAuthStore()
const gamificationStore = useGamificationStore()

const courseId = computed(() => Number(route.params.courseId))
const isTeacher = computed(() => ['teacher', 'admin'].includes(auth.user?.role))

onMounted(async () => {
  await gamificationStore.fetchLeaderboard(courseId.value)
})
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <StudentAppHeader
      v-if="!isTeacher"
      title="Clasificación del curso"
      subtitle="Ranking por puntos totales"
      :course-id="courseId"
      show-back
    />

    <main class="max-w-6xl mx-auto px-4 py-8" :class="isTeacher ? 'px-0 py-0 max-w-none' : ''">
      <div v-if="gamificationStore.loading" class="text-slate-500">Cargando clasificación...</div>
      <div
        v-else-if="gamificationStore.error"
        class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
        role="alert"
      >
        {{ gamificationStore.error }}
      </div>
      <CourseLeaderboard
        v-else
        :leaderboard="gamificationStore.leaderboard"
        :current-user-id="auth.user?.id"
      />
    </main>
  </div>
</template>
