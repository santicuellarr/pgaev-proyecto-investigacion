<script setup>
import { RouterView } from 'vue-router'
import EmailVerificationBanner from '@/components/EmailVerificationBanner.vue'
</script>

<template>
  <div id="contenido-principal">
    <EmailVerificationBanner />
    <RouterView />
  </div>
</template>
