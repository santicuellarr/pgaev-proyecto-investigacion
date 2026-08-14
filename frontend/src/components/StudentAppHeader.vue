<script setup>
import { useRouter, RouterLink } from 'vue-router'
import NotificationBell from '@/components/NotificationBell.vue'
import { useAuthStore } from '@/stores/auth'
import StudentGamificationWidget from '@/components/StudentGamificationWidget.vue'

defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  courseId: {
    type: Number,
    default: null,
  },
  showBack: {
    type: Boolean,
    default: false,
  },
})

const router = useRouter()
const auth = useAuthStore()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 py-4">
      <button
        v-if="showBack"
        type="button"
        class="mb-3 text-sm text-indigo-600 hover:text-indigo-500 font-medium"
        @click="router.back()"
      >
        ← Volver
      </button>

      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-xl font-bold text-slate-900">{{ title }}</h1>
          <p v-if="subtitle" class="text-sm text-slate-500 mt-0.5">{{ subtitle }}</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap justify-end">
          <NotificationBell />
          <RouterLink
            to="/student/profile"
            class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 transition"
          >
            Mi perfil
          </RouterLink>
          <StudentGamificationWidget :course-id="courseId" />
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition"
            @click="handleLogout"
          >
            Cerrar sesión
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
