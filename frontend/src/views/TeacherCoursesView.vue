<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTeacherStore } from '@/stores/teacher'

const router = useRouter()
const teacher = useTeacherStore()

const showModal = ref(false)
const editingId = ref(null)
const copiedId = ref(null)

const colorOptions = ['#4f46e5', '#0f766e', '#b45309', '#be123c', '#0369a1', '#7c3aed']

const form = reactive({
  title: '',
  description: '',
  color: '#4f46e5',
  status: 'published',
})

const statusLabels = {
  draft: 'Borrador',
  published: 'Publicado',
  archived: 'Archivado',
}

onMounted(async () => {
  await teacher.fetchCourses()
})

function openCreate() {
  teacher.clearMessages()
  editingId.value = null
  form.title = ''
  form.description = ''
  form.color = '#4f46e5'
  form.status = 'published'
  showModal.value = true
}

function openEdit(course) {
  teacher.clearMessages()
  editingId.value = course.id
  form.title = course.title
  form.description = course.description || ''
  form.color = course.color || '#4f46e5'
  form.status = course.status || 'published'
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  const payload = {
    title: form.title,
    description: form.description || null,
    color: form.color,
    status: form.status,
  }

  try {
    if (editingId.value) {
      await teacher.updateCourse(editingId.value, payload)
    } else {
      await teacher.createCourse(payload)
    }
    showModal.value = false
    await teacher.fetchCourses()
  } catch {
    // teacher.error
  }
}

async function copyCode(course) {
  await navigator.clipboard.writeText(course.invite_code)
  copiedId.value = course.id
  setTimeout(() => {
    if (copiedId.value === course.id) {
      copiedId.value = null
    }
  }, 1500)
}

function statusClass(status) {
  if (status === 'published') {
    return 'bg-emerald-100 text-emerald-700'
  }
  if (status === 'draft') {
    return 'bg-amber-100 text-amber-800'
  }
  return 'bg-slate-100 text-slate-600'
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <p class="text-sm text-slate-600">Cursos que administras, con código de invitación y estado.</p>
      <button
        type="button"
        class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-semibold"
        @click="openCreate"
      >
        + Nuevo curso
      </button>
    </div>

    <div
      v-if="teacher.success && !showModal"
      class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
    >
      {{ teacher.success }}
    </div>
    <div
      v-if="teacher.error && !showModal"
      class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ teacher.error }}
    </div>

    <div v-if="teacher.loading && !teacher.courses.length" class="text-slate-500">Cargando cursos...</div>

    <div
      v-else-if="!teacher.courses.length"
      class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
    >
      <p class="text-slate-600">Aún no has creado ningún curso.</p>
      <button
        type="button"
        class="mt-4 rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold"
        @click="openCreate"
      >
        Crear tu primer curso
      </button>
    </div>

    <div v-else class="grid gap-4 md:grid-cols-2">
      <article
        v-for="course in teacher.courses"
        :key="course.id"
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm overflow-hidden"
      >
        <div class="h-2 -mt-6 -mx-6 mb-4" :style="{ backgroundColor: course.color || '#4f46e5' }" />
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ course.title }}</h2>
            <p class="mt-1 text-sm text-slate-600 line-clamp-2">{{ course.description || 'Sin descripción' }}</p>
          </div>
          <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(course.status)">
            {{ statusLabels[course.status] || course.status }}
          </span>
        </div>

        <div class="mt-4 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Código de invitación</p>
            <p class="mt-1 text-xl font-bold tracking-widest text-slate-900 font-mono">{{ course.invite_code }}</p>
          </div>
          <button
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700"
            @click="copyCode(course)"
          >
            {{ copiedId === course.id ? 'Copiado' : 'Copiar' }}
          </button>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs text-slate-600">
          <div class="rounded-lg bg-slate-50 py-2">
            <p class="font-bold text-slate-900">{{ course.enrolled_count ?? 0 }}</p>
            <p>Inscritos</p>
          </div>
          <div class="rounded-lg bg-slate-50 py-2">
            <p class="font-bold text-slate-900">{{ course.activities_count ?? 0 }}</p>
            <p>Actividades</p>
          </div>
          <div class="rounded-lg bg-slate-50 py-2">
            <p class="font-bold text-amber-700">{{ course.pending_submissions_count ?? 0 }}</p>
            <p>Pendientes</p>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold"
            @click="router.push({ name: 'teacher-course-detail', params: { id: course.id } })"
          >
            Abrir curso
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700"
            @click="openEdit(course)"
          >
            Editar
          </button>
        </div>
      </article>
    </div>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
      @click.self="closeModal"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">
          {{ editingId ? 'Editar curso' : 'Crear curso' }}
        </h3>
        <div v-if="teacher.error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {{ teacher.error }}
        </div>
        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Título</label>
            <input v-model="form.title" required class="w-full rounded-lg border border-slate-300 px-4 py-2.5" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
            <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-slate-300 px-4 py-2.5" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Estado</label>
            <select v-model="form.status" class="w-full rounded-lg border border-slate-300 px-4 py-2.5">
              <option value="draft">Borrador</option>
              <option value="published">Publicado</option>
              <option value="archived">Archivado</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Color</label>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="color in colorOptions"
                :key="color"
                type="button"
                class="h-8 w-8 rounded-full border-2"
                :class="form.color === color ? 'border-slate-900' : 'border-transparent'"
                :style="{ backgroundColor: color }"
                @click="form.color = color"
              />
            </div>
          </div>
          <div class="flex justify-end gap-3">
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="closeModal">
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="teacher.saving"
              class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-50"
            >
              {{ teacher.saving ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
