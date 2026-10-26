<script setup>
import { onMounted, ref } from 'vue'
import { useTeacherStore } from '@/stores/teacher'

const teacher = useTeacherStore()
const rejectId = ref(null)
const rejectReason = ref('')
const acting = ref(false)

onMounted(async () => {
  teacher.clearMessages()
  await teacher.fetchPendingRedemptions()
})

function formatDate(value) {
  if (!value) {
    return '—'
  }

  return new Date(value).toLocaleString('es-CO')
}

async function handleApprove(redemption) {
  acting.value = true
  teacher.error = null
  try {
    await teacher.approveRedemption(redemption.id)
  } catch (err) {
    teacher.error = err.response?.data?.message || 'No se pudo aprobar el canje.'
  } finally {
    acting.value = false
  }
}

function openReject(redemption) {
  rejectId.value = redemption.id
  rejectReason.value = ''
}

async function handleReject() {
  if (!rejectId.value || !rejectReason.value.trim()) {
    return
  }

  acting.value = true
  teacher.error = null
  try {
    await teacher.rejectRedemption(rejectId.value, rejectReason.value.trim())
    rejectId.value = null
    rejectReason.value = ''
  } catch (err) {
    teacher.error = err.response?.data?.message || err.response?.data?.errors?.reason?.[0] || 'No se pudo rechazar el canje.'
  } finally {
    acting.value = false
  }
}
</script>

<template>
  <section class="space-y-4">
    <div>
      <h2 class="text-xl font-bold text-slate-900">Canjes pendientes</h2>
      <p class="mt-1 text-sm text-slate-600">
        {{ teacher.pendingRedemptionCount }} solicitud{{ teacher.pendingRedemptionCount === 1 ? '' : 'es' }} por aprobar.
      </p>
    </div>

    <div v-if="teacher.error" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ teacher.error }}
    </div>

    <article
      v-for="redemption in teacher.pendingRedemptions"
      :key="redemption.id"
      class="rounded-2xl border border-slate-200 bg-white p-5"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="font-semibold text-slate-900">{{ redemption.reward?.name }}</p>
          <p class="text-sm text-slate-600">{{ redemption.user?.name }} · {{ redemption.user?.email }}</p>
          <p class="text-xs text-slate-500 mt-1">
            {{ redemption.reward?.course?.title }} · {{ redemption.reward?.cost_points }} pts ·
            {{ formatDate(redemption.redeemed_at) }}
          </p>
        </div>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded-lg bg-emerald-600 text-white px-3 py-1.5 text-sm font-semibold disabled:opacity-50"
            :disabled="acting"
            @click="handleApprove(redemption)"
          >
            Aprobar
          </button>
          <button
            type="button"
            class="rounded-lg border border-red-200 text-red-700 px-3 py-1.5 text-sm disabled:opacity-50"
            :disabled="acting"
            @click="openReject(redemption)"
          >
            Rechazar
          </button>
        </div>
      </div>

      <form v-if="rejectId === redemption.id" class="mt-4 space-y-2" @submit.prevent="handleReject">
        <label class="block text-sm font-medium">Motivo del rechazo</label>
        <textarea v-model="rejectReason" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="rejectId = null">Cancelar</button>
          <button type="submit" class="rounded-lg bg-red-600 text-white px-3 py-1.5 text-sm font-semibold" :disabled="acting">
            Confirmar rechazo
          </button>
        </div>
      </form>
    </article>

    <p v-if="!teacher.pendingRedemptions.length" class="rounded-2xl border border-dashed bg-white p-8 text-sm text-slate-500">
      No hay canjes pendientes de aprobación.
    </p>
  </section>
</template>
