<template>
  <div>
    <div v-if="!breaches.length" class="flex flex-col items-center justify-center h-48 text-slate-500 text-sm gap-2">
      <svg class="w-10 h-10 text-green-500/40" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
      </svg>
      <p class="text-green-400 font-semibold text-sm">No breaches in history</p>
    </div>
    <div v-else>
      <Bar :data="chartData" :options="chartOptions" class="max-h-48" />
    </div>
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
  Title,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const props = defineProps({
  breaches: { type: Array, default: () => [] },
})

const yearCounts = computed(() => {
  const counts = {}
  for (const b of props.breaches) {
    if (b.breach_date) {
      const yr = String(b.breach_date).substring(0, 4)
      counts[yr] = (counts[yr] || 0) + 1
    }
  }
  return counts
})

const chartData = computed(() => {
  const years  = Object.keys(yearCounts.value).sort()
  const counts = years.map(y => yearCounts.value[y])
  return {
    labels: years,
    datasets: [{
      label: 'Breaches',
      data: counts,
      backgroundColor: counts.map((_, i) => i === counts.length - 1 ? '#ef4444' : '#f97316'),
      borderRadius: 6,
      borderSkipped: false,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#1e293b',
      titleColor: '#94a3b8',
      bodyColor: '#f1f5f9',
    },
  },
  scales: {
    x: {
      grid: { color: '#1e293b' },
      ticks: { color: '#64748b' },
    },
    y: {
      grid: { color: '#1e293b' },
      ticks: { color: '#64748b', stepSize: 1 },
      beginAtZero: true,
    },
  },
}
</script>
