<template>
  <div class="w-full h-full flex items-center justify-center">
    <Radar :data="chartData" :options="chartOptions" class="max-h-64 w-full" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Radar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  RadarController,
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
} from 'chart.js'

ChartJS.register(RadarController, RadialLinearScale, PointElement, LineElement, Filler, Tooltip)

const props = defineProps({
  categories: { type: Object, default: () => ({}) },
})

function colorForScore(s) {
  if (s >= 80) return '#10b981'
  if (s >= 60) return '#3b82f6'
  if (s >= 40) return '#f59e0b'
  return '#ef4444'
}

// Shorten long category names for the radar axes
const SHORT_NAMES = {
  'Breach History':          'Breaches',
  'Data Sensitivity Exposed': 'Data Sensitivity',
  'SPF Health':              'SPF',
  'DKIM Health':             'DKIM',
  'DMARC Enforcement':       'DMARC',
  'Domain Security Posture': 'Domain',
}

const chartData = computed(() => {
  const entries = Object.entries(props.categories)
  const labels  = entries.map(([, c]) => SHORT_NAMES[c.name] ?? c.name ?? '')
  const scores  = entries.map(([, c]) => c.score ?? 0)
  const avgColor = '#3b82f6'

  return {
    labels,
    datasets: [
      {
        label: 'Score',
        data: scores,
        backgroundColor: 'rgba(59,130,246,0.15)',
        borderColor: avgColor,
        borderWidth: 2,
        pointBackgroundColor: scores.map(colorForScore),
        pointBorderColor: '#0f172a',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7,
      },
    ],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#1e293b',
      titleColor: '#94a3b8',
      bodyColor: '#f1f5f9',
      borderColor: '#334155',
      borderWidth: 1,
      callbacks: {
        label: ctx => ` ${ctx.parsed.r}/100`,
      },
    },
  },
  scales: {
    r: {
      min: 0,
      max: 100,
      ticks: {
        stepSize: 25,
        color: '#475569',
        backdropColor: 'transparent',
        font: { size: 9 },
      },
      grid:        { color: '#1e293b' },
      angleLines:  { color: '#1e293b' },
      pointLabels: { color: '#94a3b8', font: { size: 11, weight: '600' } },
    },
  },
}
</script>
