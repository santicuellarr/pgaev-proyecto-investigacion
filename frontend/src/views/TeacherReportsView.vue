<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import TeacherAnalyticsPanel from '@/components/TeacherAnalyticsPanel.vue'
import { useTeacherStore } from '@/stores/teacher'

const router = useRouter()
const teacher = useTeacherStore()

onMounted(async () => {
  await Promise.all([teacher.fetchCourses(), teacher.fetchOverview()])
})
</script>

<template>
  <div class="space-y-6">
    <h2 class="text-xl font-bold">Reportes</h2>
    <p class="text-sm text-slate-600">
      Resumen de todos tus cursos. Los CSV detallados se descargan desde cada curso, pestaña Reportes.
    </p>
    <TeacherAnalyticsPanel v-if="teacher.overview" :analytics="teacher.overview" />
    <section class="space-y-3">
      <article
        v-for="course in teacher.courses"
        :key="course.id"
        class="rounded-2xl border bg-white p-4 flex flex-wrap items-center justify-between gap-3"
      >
        <p class="font-semibold">{{ course.title }}</p>
        <button
          type="button"
          class="rounded-lg bg-indigo-600 text-white px-3 py-1.5 text-sm font-semibold"
          @click="router.push({ name: 'teacher-course-detail', params: { id: course.id } })"
        >
          Abrir analíticas
        </button>
      </article>
    </section>
  </div>
</template>
