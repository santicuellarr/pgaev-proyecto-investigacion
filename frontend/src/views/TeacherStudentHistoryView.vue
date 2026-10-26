<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTeacherStore } from '@/stores/teacher'

const route = useRoute()
const router = useRouter()
const teacher = useTeacherStore()
const entries = ref([])

onMounted(async () => {
  entries.value = await teacher.fetchStudentHistory(
    Number(route.params.id),
    Number(route.params.userId),
  )
})
</script>

<template>
  <section class="space-y-4">
    <button type="button" class="text-sm text-indigo-600 font-medium" @click="router.back()">
      ← Volver
    </button>
    <h2 class="text-xl font-bold">Historial del estudiante</h2>
    <p v-if="teacher.error" class="text-sm text-red-700">{{ teacher.error }}</p>
    <article
      v-for="entry in entries"
      :key="`${entry.type}-${entry.meta?.id}-${entry.occurred_at}`"
      class="rounded-2xl border bg-white p-4"
    >
      <p class="font-semibold">{{ entry.title }}</p>
      <p class="text-xs text-slate-500 mt-1">{{ entry.type }} · {{ entry.occurred_at }}</p>
      <p v-if="entry.delta != null" class="text-sm mt-1">{{ entry.delta > 0 ? '+' : '' }}{{ entry.delta }} pts</p>
    </article>
    <p v-if="!entries.length && !teacher.error" class="text-sm text-slate-500">Sin eventos en el historial.</p>
  </section>
</template>
