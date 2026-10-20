<script setup>
import { computed } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  kind: {
    type: String,
    default: 'level',
  },
  message: {
    type: String,
    default: '',
  },
})

const pieces = computed(() =>
  Array.from({ length: 24 }, (_, index) => ({
    id: index,
    left: `${(index * 17) % 100}%`,
    delay: `${(index % 8) * 40}ms`,
    color: ['#6366f1', '#f59e0b', '#10b981', '#ec4899', '#8b5cf6'][index % 5],
  })),
)

const heading = computed(() =>
  props.kind === 'badge' ? '¡Nueva insignia!' : '¡Subiste de nivel!',
)
</script>

<template>
  <div
    v-if="show"
    class="celebration-overlay pointer-events-none fixed inset-0 z-[80] flex items-start justify-center pt-24"
    role="status"
    aria-live="polite"
  >
    <div
      v-for="piece in pieces"
      :key="piece.id"
      class="celebration-piece"
      :style="{ left: piece.left, background: piece.color, animationDelay: piece.delay }"
    />
    <div class="relative rounded-2xl border border-indigo-200 bg-white/95 px-6 py-4 shadow-xl">
      <p class="text-center text-lg font-bold text-indigo-700">{{ heading }}</p>
      <p v-if="message" class="mt-1 max-w-sm text-center text-sm text-slate-600">{{ message }}</p>
    </div>
  </div>
</template>

<style scoped>
.celebration-piece {
  position: absolute;
  top: -12px;
  width: 8px;
  height: 14px;
  border-radius: 2px;
  animation: celebrate-fall 1.2s ease-in forwards;
}

@keyframes celebrate-fall {
  0% {
    transform: translateY(0) rotate(0deg);
    opacity: 1;
  }
  100% {
    transform: translateY(70vh) rotate(220deg);
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .celebration-piece {
    display: none;
  }

  .celebration-overlay {
    animation: none;
  }
}
</style>
