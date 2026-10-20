<script setup>
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
  name: '',
  email: '',
  password: '',
})

async function handleSubmit() {
  try {
    await auth.register({
      name: form.name,
      email: form.email,
      password: form.password,
    })
    router.push({ name: 'home' })
  } catch {
    // El mensaje de error se muestra desde auth.error
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-white tracking-tight">PGAEV</h1>
        <p class="text-slate-200 mt-2">Crea tu cuenta de estudiante</p>
      </div>

      <form
        class="bg-white/10 backdrop-blur-md border border-white/10 rounded-2xl p-8 shadow-2xl"
        @submit.prevent="handleSubmit"
      >
        <div
          v-if="auth.error"
          class="mb-6 rounded-lg bg-red-500/10 border border-red-500/30 px-4 py-3 text-sm text-red-300"
          role="alert"
        >
          {{ auth.error }}
        </div>

        <div class="space-y-5">
          <div>
            <label for="name" class="block text-sm font-medium text-slate-300 mb-1.5">
              Nombre completo
            </label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              required
              autocomplete="name"
              class="w-full rounded-lg bg-slate-900/50 border border-slate-600 text-white placeholder-slate-500 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              placeholder="Tu nombre"
            />
          </div>

          <div>
            <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">
              Correo electrónico
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              autocomplete="email"
              class="w-full rounded-lg bg-slate-900/50 border border-slate-600 text-white placeholder-slate-500 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              placeholder="tu@correo.com"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">
              Contraseña
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              minlength="8"
              autocomplete="new-password"
              class="w-full rounded-lg bg-slate-900/50 border border-slate-600 text-white placeholder-slate-500 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              placeholder="Mínimo 8 caracteres"
            />
          </div>
        </div>

        <button
          type="submit"
          :disabled="auth.loading"
          class="mt-6 w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold py-2.5 transition focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-slate-900"
        >
          {{ auth.loading ? 'Creando cuenta...' : 'Crear cuenta' }}
        </button>

        <p class="mt-6 text-center text-sm text-slate-400">
          ¿Ya tienes cuenta?
          <RouterLink to="/login" class="text-indigo-400 hover:text-indigo-300 font-medium">
            Inicia sesión
          </RouterLink>
        </p>
      </form>
    </div>
  </div>
</template>
