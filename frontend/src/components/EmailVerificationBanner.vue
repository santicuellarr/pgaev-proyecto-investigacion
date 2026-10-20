<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const sending = ref(false)
const feedback = ref('')

async function handleResend() {
  if (sending.value) {
    return
  }

  sending.value = true
  feedback.value = ''

  try {
    const data = await auth.resendVerification()
    feedback.value = data.message || 'Enlace de verificación enviado.'
  } catch {
    feedback.value = auth.error || 'No se pudo reenviar el enlace.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div
    v-if="auth.needsEmailVerification"
    class="bg-amber-50 border-b border-amber-200"
  >
    <div class="max-w-6xl mx-auto px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <p class="text-sm text-amber-900">
        Verifica tu correo para usar cursos, actividades y recompensas. Revisa el buzón o reenvía el enlace.
      </p>
      <div class="flex items-center gap-3">
        <p v-if="feedback" class="text-xs text-amber-800">{{ feedback }}</p>
        <button
          type="button"
          class="rounded-lg bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white px-3 py-1.5 text-sm font-semibold transition"
          :disabled="sending"
          @click="handleResend"
        >
          {{ sending ? 'Enviando...' : 'Reenviar verificación' }}
        </button>
      </div>
    </div>
  </div>
</template>
