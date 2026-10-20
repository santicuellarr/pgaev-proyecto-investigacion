<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import NotificationBell from '@/components/NotificationBell.vue'
import { useAuthStore } from '@/stores/auth'
import { useTeacherStore } from '@/stores/teacher'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const teacher = useTeacherStore()
const menuOpen = ref(false)

onMounted(async () => {
  try {
    await teacher.fetchPendingRedemptions()
  } catch {
    // sin canjes
  }
})

const navItems = [
  { name: 'teacher-dashboard', label: 'Dashboard', to: '/teacher' },
  { name: 'teacher-courses', label: 'Mis cursos', to: '/teacher/courses' },
  { name: 'teacher-rewards', label: 'Recompensas', to: '/teacher/rewards' },
  { name: 'teacher-badges', label: 'Insignias', to: '/teacher/badges' },
  { name: 'teacher-redemptions', label: 'Canjes', to: '/teacher/redemptions' },
  { name: 'teacher-reports', label: 'Reportes', to: '/teacher/reports' },
]

const pageTitle = computed(() => {
  const current = navItems.find((item) => item.name === route.name)
  if (route.name === 'teacher-course-detail') {
    return 'Detalle del curso'
  }
  if (route.name === 'teacher-student-history') {
    return 'Historial del estudiante'
  }
  return current?.label || 'Panel docente'
})

function isActive(item) {
  if (item.name === 'teacher-dashboard') {
    return route.name === 'teacher-dashboard'
  }

  if (item.name === 'teacher-courses') {
    return route.name === 'teacher-courses' || route.name === 'teacher-course-detail' || route.name === 'teacher-student-history'
  }

  return route.name === item.name
}

function closeMenu() {
  menuOpen.value = false
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <div
      v-if="menuOpen"
      class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
      @click="closeMenu"
    />

    <aside
      class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-slate-100 transform transition-transform lg:translate-x-0"
      :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="px-5 py-5 border-b border-slate-800">
        <p class="text-lg font-bold tracking-tight">PGAEV</p>
        <p class="mt-1 text-xs text-slate-400">
          {{ auth.user?.role === 'admin' ? 'Panel docente (admin)' : 'Panel docente' }}
        </p>
      </div>
      <nav class="p-3 space-y-1">
        <RouterLink
          v-if="auth.user?.role === 'admin'"
          to="/admin"
          class="block rounded-lg px-3 py-2 text-sm font-medium text-amber-300 hover:bg-slate-800 hover:text-white"
          @click="closeMenu"
        >
          Administración
        </RouterLink>
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="item.to"
          class="block rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="isActive(item) ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
          @click="closeMenu"
        >
          <span class="flex items-center justify-between gap-2">
            {{ item.label }}
            <span
              v-if="item.name === 'teacher-redemptions' && teacher.pendingRedemptionCount"
              class="rounded-full bg-amber-400 text-slate-900 px-2 py-0.5 text-[11px] font-bold"
            >
              {{ teacher.pendingRedemptionCount }}
            </span>
          </span>
        </RouterLink>
      </nav>
    </aside>

    <div class="lg:pl-64">
      <header class="sticky top-0 z-20 bg-white border-b border-slate-200">
        <div class="px-4 py-3 flex items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="lg:hidden rounded-lg border border-slate-300 px-3 py-2 text-slate-700"
              :aria-label="menuOpen ? 'Cerrar menú' : 'Abrir menú'"
              :aria-expanded="menuOpen"
              @click="menuOpen = !menuOpen"
            >
              ☰
            </button>
            <div>
              <h1 class="text-lg font-bold text-slate-900">{{ pageTitle }}</h1>
              <p v-if="auth.user" class="text-xs text-slate-500">
                {{ auth.user.name }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <NotificationBell />
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
              @click="handleLogout"
            >
              Cerrar sesión
            </button>
          </div>
        </div>
      </header>

      <main class="p-4 md:p-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>
