<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useNotificationStore } from '@/stores/notification'

const notificationStore = useNotificationStore()

const isOpen = ref(false)
const menuRef = ref(null)

const hasUnread = computed(() => notificationStore.unreadCount > 0)

const badgeLabel = computed(() => {
  if (notificationStore.unreadCount > 9) {
    return '9+'
  }

  return String(notificationStore.unreadCount)
})

function toggleMenu() {
  isOpen.value = !isOpen.value
}

function closeMenu() {
  isOpen.value = false
}

function handleDocumentClick(event) {
  if (!menuRef.value?.contains(event.target)) {
    closeMenu()
  }
}

async function handleNotificationClick(notification) {
  if (!notification.is_read) {
    try {
      await notificationStore.markAsRead(notification.id)
    } catch {
      // El error queda disponible en notificationStore.error
    }
  }
}

function formatDate(value) {
  return new Intl.DateTimeFormat('es-ES', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(async () => {
  document.addEventListener('click', handleDocumentClick)

  try {
    await notificationStore.fetchNotifications()
  } catch {
    // El dropdown mostrará el estado vacío o error silenciosamente
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleDocumentClick)
})
</script>

<template>
  <div ref="menuRef" class="relative">
    <button
      type="button"
      class="relative rounded-lg border border-slate-300 p-2 text-slate-600 hover:bg-slate-100 transition"
      aria-label="Notificaciones"
      :aria-expanded="isOpen"
      @click.stop="toggleMenu"
    >
      <svg
        class="h-5 w-5"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="1.8"
        aria-hidden="true"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
        />
      </svg>

      <span
        v-if="hasUnread"
        class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
      >
        {{ badgeLabel }}
      </span>
    </button>

    <div
      v-if="isOpen"
      class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
      @click.stop
    >
      <div class="border-b border-slate-100 px-4 py-3">
        <h2 class="text-sm font-semibold text-slate-900">Notificaciones</h2>
        <p class="text-xs text-slate-500">
          {{ hasUnread ? `${notificationStore.unreadCount} sin leer` : 'Estás al día' }}
        </p>
      </div>

      <div v-if="notificationStore.loading" class="px-4 py-6 text-sm text-slate-500">
        Cargando notificaciones...
      </div>

      <div
        v-else-if="!notificationStore.notifications.length"
        class="px-4 py-6 text-sm text-slate-500"
      >
        No tienes notificaciones recientes.
      </div>

      <ul v-else class="max-h-80 overflow-y-auto divide-y divide-slate-100">
        <li
          v-for="notification in notificationStore.notifications"
          :key="notification.id"
        >
          <button
            type="button"
            class="w-full px-4 py-3 text-left transition hover:bg-slate-50"
            :class="notification.is_read ? 'bg-white' : 'bg-indigo-50/60'"
            @click="handleNotificationClick(notification)"
          >
            <div class="flex items-start justify-between gap-3">
              <p class="text-sm font-semibold text-slate-900">
                {{ notification.title }}
              </p>
              <span
                v-if="!notification.is_read"
                class="mt-1 h-2 w-2 shrink-0 rounded-full bg-red-500"
                aria-hidden="true"
              />
            </div>
            <p class="mt-1 text-sm text-slate-600">
              {{ notification.message }}
            </p>
            <p class="mt-2 text-xs text-slate-400">
              {{ formatDate(notification.created_at) }}
            </p>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
