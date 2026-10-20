<script setup>
import { computed, reactive, ref } from 'vue'

const props = defineProps({
  posts: { type: Array, default: () => [] },
  canPost: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
  success: { type: String, default: '' },
})

const emit = defineEmits(['submit'])

const body = ref('')
const replyTo = ref(null)

const roots = computed(() => props.posts.filter((post) => !post.parent_id))

function repliesOf(postId) {
  return props.posts.filter((post) => post.parent_id === postId)
}

function formatDate(value) {
  if (!value) {
    return ''
  }

  return new Date(value).toLocaleString('es-CO')
}

function submit(parentId = null) {
  const text = parentId ? replyDraft(parentId) : body.value.trim()
  if (!text) {
    return
  }

  emit('submit', { body: text, parentId })
  if (parentId) {
    replyTo.value = null
    replyBodies[parentId] = ''
  } else {
    body.value = ''
  }
}

const replyBodies = reactive({})

function replyDraft(postId) {
  return (replyBodies[postId] || '').trim()
}

function setReplyBody(postId, value) {
  replyBodies[postId] = value
}
</script>

<template>
  <div class="space-y-4">
    <p v-if="success" class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
      {{ success }}
    </p>
    <p v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ error }}
    </p>

    <article v-for="post in roots" :key="post.id" class="rounded-xl border border-slate-200 p-4 space-y-3">
      <div class="flex justify-between gap-3 text-sm">
        <p class="font-semibold text-slate-900">{{ post.user?.name }}</p>
        <p class="text-xs text-slate-500">{{ formatDate(post.created_at) }}</p>
      </div>
      <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ post.body }}</p>
      <button
        v-if="canPost"
        type="button"
        class="text-xs font-semibold text-indigo-600"
        @click="replyTo = replyTo === post.id ? null : post.id"
      >
        Responder
      </button>
      <form v-if="canPost && replyTo === post.id" class="space-y-2" @submit.prevent="submit(post.id)">
        <textarea
          rows="2"
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          :value="replyBodies[post.id] || ''"
          @input="setReplyBody(post.id, $event.target.value)"
        />
        <button type="submit" class="rounded-lg bg-indigo-600 text-white px-3 py-1.5 text-xs font-semibold" :disabled="submitting">
          Publicar respuesta
        </button>
      </form>
      <div v-for="reply in repliesOf(post.id)" :key="reply.id" class="ml-4 rounded-lg bg-slate-50 border border-slate-100 p-3">
        <div class="flex justify-between gap-3 text-sm">
          <p class="font-semibold">{{ reply.user?.name }}</p>
          <p class="text-xs text-slate-500">{{ formatDate(reply.created_at) }}</p>
        </div>
        <p class="mt-1 text-sm whitespace-pre-wrap">{{ reply.body }}</p>
      </div>
    </article>

    <p v-if="!posts.length" class="text-sm text-slate-500">Todavía no hay aportes en este foro.</p>

    <form v-if="canPost" class="space-y-2" @submit.prevent="submit(null)">
      <label class="block text-sm font-medium">Tu aporte</label>
      <textarea v-model="body" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
      <button
        type="submit"
        class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-50"
        :disabled="submitting || !body.trim()"
      >
        {{ submitting ? 'Publicando...' : 'Publicar' }}
      </button>
    </form>
  </div>
</template>
