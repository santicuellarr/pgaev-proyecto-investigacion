<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import StudentAppHeader from '@/components/StudentAppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useGamificationStore } from '@/stores/gamification'
import { useRewardStore } from '@/stores/reward'

const route = useRoute()
const auth = useAuthStore()
const gamificationStore = useGamificationStore()
const rewardStore = useRewardStore()

const courseId = computed(() => Number(route.params.courseId))
const confirmReward = ref(null)
const pendingIdempotencyKey = ref(null)

const myStats = computed(() => {
  if (!auth.user) {
    return null
  }

  return gamificationStore.getMyStats(auth.user.id)
})

const myPoints = computed(() => myStats.value?.total_points ?? 0)

function canAfford(reward) {
  return myPoints.value >= reward.cost_points
}

onMounted(async () => {
  await Promise.all([
    gamificationStore.fetchLeaderboard(courseId.value),
    rewardStore.fetchRewards(courseId.value),
  ])
})

function openConfirm(reward) {
  if (!canAfford(reward) || rewardStore.redeeming) {
    return
  }

  rewardStore.clearMessages()
  confirmReward.value = reward
  pendingIdempotencyKey.value = null
}

function closeConfirm() {
  confirmReward.value = null
  pendingIdempotencyKey.value = null
}

async function handleRedeem() {
  if (!confirmReward.value || rewardStore.redeeming) {
    return
  }

  if (!pendingIdempotencyKey.value) {
    pendingIdempotencyKey.value = crypto.randomUUID()
  }

  try {
    await rewardStore.redeemReward(confirmReward.value.id, pendingIdempotencyKey.value)
    await Promise.all([
      gamificationStore.fetchLeaderboard(courseId.value),
      rewardStore.fetchRewards(courseId.value),
    ])
    closeConfirm()
  } catch {
    // El error se muestra desde rewardStore.error
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <StudentAppHeader
      title="Tienda de Recompensas"
      subtitle="Canjea tus puntos por beneficios exclusivos"
      :course-id="courseId"
      show-back
    />

    <main class="max-w-6xl mx-auto px-4 py-8">
      <div class="mb-6 rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-yellow-50 p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-sm text-amber-800 font-medium">Tu saldo disponible</p>
          <p class="text-3xl font-bold text-amber-900">{{ myPoints }} <span class="text-lg font-semibold">pts</span></p>
        </div>
        <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-800 px-4 py-2 text-sm font-semibold">
          Nivel {{ myStats?.current_level ?? 1 }}
        </span>
      </div>

      <div
        v-if="rewardStore.success"
        class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700"
        role="status"
      >
        {{ rewardStore.success }}
      </div>

      <div
        v-if="rewardStore.error && !confirmReward"
        class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
        role="alert"
      >
        {{ rewardStore.error }}
      </div>

      <div v-if="rewardStore.loading" class="text-slate-500">
        Cargando recompensas...
      </div>

      <div
        v-else-if="!rewardStore.rewards.length"
        class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
      >
        <p class="text-slate-600">No hay recompensas disponibles en este curso.</p>
      </div>

      <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="reward in rewardStore.rewards"
          :key="reward.id"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm hover:shadow-md transition flex flex-col"
        >
          <div class="flex items-start justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">{{ reward.name }}</h2>
            <span class="shrink-0 rounded-full bg-amber-100 text-amber-800 px-3 py-1 text-sm font-bold">
              {{ reward.cost_points }} pts
            </span>
          </div>

          <p class="mt-3 text-sm text-slate-500">
            Stock disponible: <span class="font-medium text-slate-700">{{ reward.stock }}</span>
          </p>

          <button
            type="button"
            class="mt-auto pt-5 w-full rounded-xl px-4 py-2.5 text-sm font-semibold transition"
            :class="canAfford(reward)
              ? 'bg-indigo-600 hover:bg-indigo-500 text-white'
              : 'bg-slate-200 text-slate-500 cursor-not-allowed'"
            :disabled="!canAfford(reward) || rewardStore.redeeming"
            @click="openConfirm(reward)"
          >
            {{ canAfford(reward) ? 'Canjear' : 'Puntos insuficientes' }}
          </button>
        </article>
      </div>
    </main>

    <div
      v-if="confirmReward"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
      @click.self="closeConfirm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-semibold text-slate-900">Confirmar canje</h3>
        <p class="mt-2 text-sm text-slate-600">
          ¿Deseas canjear <strong>{{ confirmReward.name }}</strong> por
          <span class="font-semibold text-amber-800">{{ confirmReward.cost_points }} puntos</span>?
        </p>

        <div
          v-if="rewardStore.error"
          class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
          role="alert"
        >
          {{ rewardStore.error }}
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            :disabled="rewardStore.redeeming"
            @click="closeConfirm"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white px-4 py-2 text-sm font-semibold transition"
            :disabled="rewardStore.redeeming"
            @click="handleRedeem"
          >
            <svg
              v-if="rewardStore.redeeming"
              class="h-4 w-4 animate-spin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            {{ rewardStore.redeeming ? 'Canjeando...' : 'Confirmar canje' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
