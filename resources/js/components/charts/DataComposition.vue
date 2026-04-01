<template>
  <div>
    <div v-if="!attributes.length" class="flex flex-col items-center justify-center h-48 text-slate-500 text-sm gap-2">
      <svg class="w-10 h-10 text-green-500/40" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
      </svg>
      <p class="text-green-400 font-semibold text-sm">No exposed data</p>
    </div>
    <div v-else>
      <Doughnut :data="chartData" :options="chartOptions" class="max-h-52" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  attributes: { type: Array, default: () => [] },
})

const SEVERITY = {
  password: 50, passwords: 50, ssn: 50,
  'date of birth': 15, dob: 15,
  phone: 10, name: 10, address: 10,
  ip: 5, employer: 5, username: 5, email: 3,
}

const COLORS = [
  '#ef4444', '#f97316', '#f59e0b', '#eab308',
  '#84cc16', '#22c55e', '#06b6d4', '#3b82f6',
  '#8b5cf6', '#ec4899',
]

const chartData = computed(() => {
  const items = props.attributes.slice(0, 10).map((attr, i) => {
    const lower = attr.toLowerCase()
    let weight = 3
    for (const [key, val] of Object.entries(SEVERITY)) {
      if (lower.includes(key)) { weight = val; break }
    }
    return { label: attr, weight, color: COLORS[i % COLORS.length] }
  })

  return {
    labels: items.map(i => i.label),
    datasets: [{
      data: items.map(i => i.weight),
      backgroundColor: items.map(i => i.color),
      borderColor: '#0f172a',
      borderWidth: 2,
      hoverOffset: 6,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '60%',
  plugins: {
    legend: {
      position: 'right',
      labels: {
        color: '#94a3b8',
        font: { size: 11 },
        padding: 8,
        boxWidth: 12,
        boxHeight: 12,
      },
    },
    tooltip: {
      backgroundColor: '#1e293b',
      titleColor: '#94a3b8',
      bodyColor: '#f1f5f9',
    },
  },
}
</script>
