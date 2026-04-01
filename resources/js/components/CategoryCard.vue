<template>
  <div :class="cardClass" class="rounded-2xl p-5 border backdrop-blur-sm transition-transform hover:scale-[1.01]">
    <div class="flex items-start justify-between mb-3">
      <p class="text-sm font-semibold text-slate-200 leading-snug pr-2">{{ name }}</p>
      <span :class="statusIconClass" class="text-xl flex-shrink-0">{{ statusIcon }}</span>
    </div>
    <div class="flex items-end gap-1 mb-2">
      <span class="text-4xl font-black" :style="{ color: scoreColor }">{{ score }}</span>
      <span class="text-slate-500 text-sm mb-1">/100</span>
    </div>
    <div class="w-full bg-slate-700/50 rounded-full h-1.5 mb-3">
      <div class="h-1.5 rounded-full transition-all duration-700" :style="{ width: score + '%', backgroundColor: scoreColor }"></div>
    </div>
    <p class="text-xs text-slate-400 leading-relaxed">{{ rationale }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  name:      String,
  score:     Number,
  status:    String,
  rationale: String,
})

const scoreColor = computed(() => {
  if (props.score >= 80) return '#10b981'
  if (props.score >= 60) return '#3b82f6'
  if (props.score >= 40) return '#f59e0b'
  return '#ef4444'
})

const cardClass = computed(() => {
  const map = {
    pass:        'bg-green-500/5  border-green-500/20',
    warn:        'bg-yellow-500/5 border-yellow-500/20',
    fail:        'bg-red-500/5    border-red-500/20',
    pending:     'bg-slate-800/50 border-slate-600/30',
    unavailable: 'bg-slate-800/50 border-slate-600/30',
  }
  return map[props.status] ?? 'bg-slate-900/70 border-slate-700/50'
})

const statusIcon = computed(() => ({
  pass: '✅', warn: '⚠️', fail: '❌', pending: '⏳', unavailable: '❓'
}[props.status] ?? '—'))

const statusIconClass = computed(() => '')
</script>
