<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-slate-900">PGAEV</h1>
          <p v-if="auth.user" class="text-sm text-slate-500">
            Bienvenido, {{ auth.user.name }}
            <span class="ml-2 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">
              {{ auth.user.role }}
            </span>
          </p>
        </div>
        <button
          type="button"
          class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition"
          @click="handleLogout"
        >
          Cerrar sesión
        </button>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-10">
      <div class="rounded-2xl bg-white border border-slate-200 p-8 shadow-sm">
        <h2 class="text-2xl font-semibold text-slate-900">Panel principal</h2>
        <p class="mt-2 text-slate-600">
          Has iniciado sesión correctamente. El módulo de autenticación está funcionando.
        </p>
      </div>
    </main>
  </div>
</template>
