<template>
  <div>
    <Bar :data="chartData" :options="chartOptions" class="max-h-56" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Tooltip,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip)

const props = defineProps({
  categories: { type: Object, default: () => ({}) },
})

function colorForScore(s) {
  if (s >= 80) return '#10b981'
  if (s >= 60) return '#3b82f6'
  if (s >= 40) return '#f59e0b'
  return '#ef4444'
}

const chartData = computed(() => {
  const entries = Object.entries(props.categories)
  const labels  = entries.map(([, c]) => c.name || '')
  const scores  = entries.map(([, c]) => c.score ?? 0)

  return {
    labels,
    datasets: [{
      label: 'Score',
      data: scores,
      backgroundColor: scores.map(colorForScore),
      borderRadius: 6,
      borderSkipped: false,
    }],
  }
})

const chartOptions = {
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#1e293b',
      titleColor: '#94a3b8',
      bodyColor: '#f1f5f9',
      callbacks: {
        label: ctx => ` ${ctx.parsed.x}/100`,
      },
    },
  },
  scales: {
    x: {
      min: 0,
      max: 100,
      grid: { color: '#1e293b' },
      ticks: { color: '#64748b' },
    },
    y: {
      grid: { display: false },
      ticks: { color: '#94a3b8', font: { size: 11 } },
    },
  },
}
</script>
