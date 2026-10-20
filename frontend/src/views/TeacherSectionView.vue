<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const copy = computed(() => {
  const map = {
    'teacher-rewards': {
      title: 'Recompensas',
      text: 'Las recompensas se administran dentro de cada curso. Abre Mis cursos y entra a la pestaña Recompensas.',
    },
    'teacher-badges': {
      title: 'Insignias',
      text: 'Las insignias del curso aparecen en el detalle, pestaña Insignias.',
    },
    'teacher-redemptions': {
      title: 'Canjes',
      text: 'El historial y el stock de canjes se consultan en el detalle del curso y en el ranking de estudiantes.',
    },
    'teacher-reports': {
      title: 'Reportes',
      text: 'Exporta el CSV de analítica desde el detalle del curso, pestaña Reportes.',
    },
  }

  return map[route.name] || { title: 'Sección', text: '' }
})
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-8 max-w-2xl">
    <h2 class="text-xl font-bold text-slate-900">{{ copy.title }}</h2>
    <p class="mt-3 text-sm text-slate-600">{{ copy.text }}</p>
    <button
      type="button"
      class="mt-6 rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold"
      @click="router.push({ name: 'teacher-courses' })"
    >
      Ir a Mis cursos
    </button>
  </section>
</template>
