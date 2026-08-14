<script setup>
import { computed, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/course'
import { useGamificationStore } from '@/stores/gamification'

const props = defineProps({
  courseId: {
    type: Number,
    default: null,
  },
})

const auth = useAuthStore()
const courseStore = useCourseStore()
const gamificationStore = useGamificationStore()

const activeCourseId = computed(() => {
  if (props.courseId) {
    return props.courseId
  }

  return courseStore.courses[0]?.id ?? null
})

const myStats = computed(() => {
  if (!auth.user) {
    return null
  }

  return gamificationStore.getMyStats(auth.user.id)
})

const progressPercent = computed(() => gamificationStore.getLevelProgress(myStats.value))

async function loadStats() {
  if (!activeCourseId.value) {
    if (!courseStore.courses.length) {
      await courseStore.fetchCourses()
    }
  }

  if (activeCourseId.value) {
    await gamificationStore.fetchLeaderboard(activeCourseId.value)
  }
}

onMounted(loadStats)

watch(activeCourseId, async (courseId) => {
  if (courseId) {
    await gamificationStore.fetchLeaderboard(courseId)
  }
})
</script>

<template>
  <div
    v-if="myStats"
    class="hidden md:flex items-center gap-4 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-2.5 min-w-[240px]"
  >
    <div class="flex-1">
      <div class="flex items-center justify-between text-xs font-medium text-slate-600">
        <span>Nivel {{ myStats.current_level }}</span>
        <span>{{ myStats.total_points }} pts</span>
      </div>
      <div class="mt-1.5 h-2 rounded-full bg-white/80 overflow-hidden">
        <div
          class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all duration-500"
          :style="{ width: `${progressPercent}%` }"
        />
      </div>
    </div>
  </div>
</template>
