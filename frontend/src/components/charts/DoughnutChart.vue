<script setup>
import '@/components/charts/registerCharts'
import { Doughnut } from 'vue-chartjs'

defineProps({
  data: { type: Object, required: true },
  options: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Doughnut :data="data" :options="options" />
</template>
