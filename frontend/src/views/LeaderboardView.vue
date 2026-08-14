<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import StudentAppHeader from '@/components/StudentAppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useGamificationStore } from '@/stores/gamification'

const route = useRoute()
const auth = useAuthStore()
const gamificationStore = useGamificationStore()

const courseId = computed(() => Number(route.params.courseId))

const podiumOrder = computed(() => {
  const [first, second, third] = gamificationStore.topThree

  return [
    { entry: second, rank: 2, medal: 'silver' },
    { entry: first, rank: 1, medal: 'gold' },
    { entry: third, rank: 3, medal: 'bronze' },
  ].filter((item) => item.entry)
})

const medalStyles = {
  gold: {
    badge: 'bg-gradient-to-br from-yellow-300 via-amber-400 to-yellow-600 text-yellow-950 shadow-[0_0_20px_rgba(251,191,36,0.45)]',
    ring: 'ring-amber-300',
    podium: 'h-28 bg-gradient-to-t from-amber-200 to-amber-50 border-amber-300',
  },
  silver: {
    badge: 'bg-gradient-to-br from-slate-200 via-slate-300 to-slate-500 text-slate-900 shadow-[0_0_16px_rgba(148,163,184,0.45)]',
    ring: 'ring-slate-300',
    podium: 'h-20 bg-gradient-to-t from-slate-200 to-slate-50 border-slate-300',
  },
  bronze: {
    badge: 'bg-gradient-to-br from-orange-300 via-amber-700 to-orange-900 text-orange-50 shadow-[0_0_16px_rgba(180,83,9,0.35)]',
    ring: 'ring-orange-300',
    podium: 'h-16 bg-gradient-to-t from-orange-200 to-orange-50 border-orange-300',
  },
}

function getInitials(name) {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

onMounted(async () => {
  await gamificationStore.fetchLeaderboard(courseId.value)
})
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <StudentAppHeader
      title="Clasificación del curso"
      subtitle="Ranking por puntos totales"
      :course-id="courseId"
      show-back
    />

    <main class="max-w-6xl mx-auto px-4 py-8">
      <div v-if="gamificationStore.loading" class="text-slate-500">
        Cargando clasificación...
      </div>

      <div
        v-else-if="gamificationStore.error"
        class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
        role="alert"
      >
        {{ gamificationStore.error }}
      </div>

      <template v-else>
        <section
          v-if="gamificationStore.topThree.length"
          class="mb-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          <h2 class="text-center text-lg font-semibold text-slate-900 mb-8">Podio</h2>

          <div class="flex items-end justify-center gap-4 md:gap-8">
            <div
              v-for="item in podiumOrder"
              :key="item.rank"
              class="flex flex-col items-center w-28 md:w-36"
            >
              <div
                class="relative rounded-full p-1 ring-4"
                :class="medalStyles[item.medal].ring"
              >
                <img
                  v-if="item.entry.user?.avatar_url"
                  :src="item.entry.user.avatar_url"
                  :alt="item.entry.user.name"
                  class="h-16 w-16 md:h-20 md:w-20 rounded-full object-cover"
                />
                <div
                  v-else
                  class="h-16 w-16 md:h-20 md:w-20 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-lg font-bold"
                >
                  {{ getInitials(item.entry.user?.name || '?') }}
                </div>
                <span
                  class="absolute -bottom-2 left-1/2 -translate-x-1/2 rounded-full px-2.5 py-0.5 text-xs font-bold"
                  :class="medalStyles[item.medal].badge"
                >
                  {{ item.rank }}°
                </span>
              </div>

              <p class="mt-4 text-sm font-semibold text-slate-900 text-center line-clamp-1">
                {{ item.entry.user?.name }}
              </p>
              <p class="mt-1 text-xl font-bold text-indigo-600">
                {{ item.entry.total_points }}
              </p>
              <p class="text-xs text-slate-500">puntos</p>

              <div
                class="mt-4 w-full rounded-t-xl border-t-4 flex items-end justify-center pb-2"
                :class="medalStyles[item.medal].podium"
              >
                <span class="text-xs font-semibold text-slate-600">Nv. {{ item.entry.current_level }}</span>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">Tabla general</h2>
          </div>

          <div
            v-if="!gamificationStore.leaderboard.length"
            class="p-10 text-center text-slate-500"
          >
            Aún no hay participantes en la clasificación.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-100 text-slate-600 uppercase text-xs tracking-wide">
                <tr>
                  <th class="px-6 py-3 text-left">Puesto</th>
                  <th class="px-6 py-3 text-left">Estudiante</th>
                  <th class="px-6 py-3 text-left">Nivel</th>
                  <th class="px-6 py-3 text-right">Puntos</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(entry, index) in gamificationStore.leaderboard"
                  :key="entry.id"
                  class="border-t border-slate-200"
                  :class="index >= 3 && index % 2 === 1 ? 'bg-slate-50' : 'bg-white'"
                >
                  <td class="px-6 py-4 font-semibold text-slate-700">
                    {{ index + 1 }}°
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <img
                        v-if="entry.user?.avatar_url"
                        :src="entry.user.avatar_url"
                        :alt="entry.user.name"
                        class="h-9 w-9 rounded-full object-cover"
                      />
                      <div
                        v-else
                        class="h-9 w-9 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold"
                      >
                        {{ getInitials(entry.user?.name || '?') }}
                      </div>
                      <span
                        class="font-medium text-slate-900"
                        :class="{ 'text-indigo-700': entry.user_id === auth.user?.id }"
                      >
                        {{ entry.user?.name }}
                        <span v-if="entry.user_id === auth.user?.id" class="text-xs text-indigo-500">(Tú)</span>
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-slate-600">
                    Nivel {{ entry.current_level }}
                  </td>
                  <td class="px-6 py-4 text-right font-bold text-indigo-600">
                    {{ entry.total_points }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>
