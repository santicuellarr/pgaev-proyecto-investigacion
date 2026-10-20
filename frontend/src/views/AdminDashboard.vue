<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import NotificationBell from '@/components/NotificationBell.vue'
import { useAdminStore } from '@/stores/admin'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const admin = useAdminStore()

const roleFilter = ref('')
const query = ref('')
const page = ref(1)

const roleLabels = {
  student: 'Estudiante',
  teacher: 'Docente',
  admin: 'Administrador',
}

const overviewCards = computed(() => {
  const data = admin.overview
  if (!data) {
    return []
  }

  return [
    { label: 'Usuarios', value: data.users_total },
    { label: 'Activos', value: data.users_active },
    { label: 'Suspendidos', value: data.users_suspended },
    { label: 'Estudiantes', value: data.users_by_role?.student ?? 0 },
    { label: 'Docentes', value: data.users_by_role?.teacher ?? 0 },
    { label: 'Administradores', value: data.users_by_role?.admin ?? 0 },
    { label: 'Cursos', value: data.courses_total },
    { label: 'Matrículas activas', value: data.enrollments_active },
    { label: 'Actividades publicadas', value: data.activities_published },
    { label: 'Envíos', value: data.submissions_total },
    { label: 'Canjes pendientes', value: data.redemptions_pending },
  ]
})

function listParams() {
  const params = { page: page.value }
  if (roleFilter.value) {
    params.role = roleFilter.value
  }
  if (query.value.trim()) {
    params.q = query.value.trim()
  }
  return params
}

async function loadUsers() {
  try {
    await admin.fetchUsers(listParams())
  } catch {
    // El error se muestra desde admin.error
  }
}

async function applyFilters() {
  page.value = 1
  await loadUsers()
}

async function changeRole(user, role) {
  if (user.role === role) {
    return
  }

  try {
    await admin.updateRole(user.id, role)
    await admin.fetchOverview()
  } catch {
    // El error se muestra desde admin.error
  }
}

async function toggleStatus(user) {
  const status = user.is_active ? 'suspended' : 'active'
  try {
    await admin.updateStatus(user.id, status)
    await admin.fetchOverview()
  } catch {
    // El error se muestra desde admin.error
  }
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

onMounted(async () => {
  await Promise.all([admin.fetchOverview(), loadUsers()])
})
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-6xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold text-slate-900">Administración</h1>
          <p class="text-sm text-slate-500">{{ auth.user?.name }} · gestión de roles y cuentas</p>
        </div>
        <div class="flex items-center gap-3">
          <NotificationBell />
          <RouterLink
            to="/teacher"
            class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
          >
            Panel docente
          </RouterLink>
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            @click="handleLogout"
          >
            Cerrar sesión
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8 space-y-8">
      <section>
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Indicadores globales</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <article
            v-for="card in overviewCards"
            :key="card.label"
            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
          >
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ card.label }}</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ card.value }}</p>
          </article>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Usuarios</h2>
            <p class="text-sm text-slate-500">{{ admin.meta.total }} cuentas registradas</p>
          </div>
          <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
            <input
              v-model="query"
              type="search"
              placeholder="Buscar nombre o correo"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
            <select v-model="roleFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
              <option value="">Todos los roles</option>
              <option value="student">Estudiante</option>
              <option value="teacher">Docente</option>
              <option value="admin">Administrador</option>
            </select>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">
              Filtrar
            </button>
          </form>
        </div>

        <p v-if="admin.error" class="text-sm text-red-700" role="alert">{{ admin.error }}</p>
        <p v-if="admin.success" class="text-sm text-emerald-700" role="status">{{ admin.success }}</p>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-left text-slate-500 border-b">
              <tr>
                <th class="py-2 pr-4 font-medium">Nombre</th>
                <th class="py-2 pr-4 font-medium">Correo</th>
                <th class="py-2 pr-4 font-medium">Rol</th>
                <th class="py-2 pr-4 font-medium">Estado</th>
                <th class="py-2 font-medium">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in admin.users" :key="user.id" class="border-b border-slate-100">
                <td class="py-3 pr-4 font-medium text-slate-900">{{ user.name }}</td>
                <td class="py-3 pr-4 text-slate-600">{{ user.email }}</td>
                <td class="py-3 pr-4">
                  <select
                    class="rounded-lg border border-slate-300 px-2 py-1"
                    :value="user.role"
                    :disabled="user.id === auth.user?.id"
                    @change="changeRole(user, $event.target.value)"
                  >
                    <option value="student">{{ roleLabels.student }}</option>
                    <option value="teacher">{{ roleLabels.teacher }}</option>
                    <option value="admin">{{ roleLabels.admin }}</option>
                  </select>
                </td>
                <td class="py-3 pr-4">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                  >
                    {{ user.is_active ? 'Activo' : 'Suspendido' }}
                  </span>
                </td>
                <td class="py-3">
                  <button
                    type="button"
                    class="rounded-lg border px-3 py-1 text-xs font-semibold"
                    :class="user.is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"
                    :disabled="user.id === auth.user?.id"
                    @click="toggleStatus(user)"
                  >
                    {{ user.is_active ? 'Suspender' : 'Reactivar' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="admin.meta.last_page > 1" class="flex items-center justify-between">
          <button
            type="button"
            class="text-sm font-medium text-indigo-600 disabled:text-slate-400"
            :disabled="page <= 1"
            @click="page -= 1; loadUsers()"
          >
            Anterior
          </button>
          <p class="text-xs text-slate-500">Página {{ admin.meta.current_page }} de {{ admin.meta.last_page }}</p>
          <button
            type="button"
            class="text-sm font-medium text-indigo-600 disabled:text-slate-400"
            :disabled="page >= admin.meta.last_page"
            @click="page += 1; loadUsers()"
          >
            Siguiente
          </button>
        </div>
      </section>
    </main>
  </div>
</template>
