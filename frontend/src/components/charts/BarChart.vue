<script setup>
import '@/components/charts/registerCharts'
import { Bar } from 'vue-chartjs'

defineProps({
  data: { type: Object, required: true },
  options: { type: Object, default: () => ({}) },
})
</script>

<template>
  <Bar :data="data" :options="options" />
</template>
