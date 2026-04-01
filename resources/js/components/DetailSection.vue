<template>
  <div class="bg-slate-900/70 border border-slate-700/50 rounded-2xl overflow-hidden backdrop-blur-sm">
    <button
      class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-slate-800/50 transition"
      @click="open = !open"
    >
      <div class="flex items-center gap-3">
        <span :class="scoreColor" class="text-2xl font-black">{{ score }}</span>
        <div>
          <p class="font-semibold text-white text-sm">{{ title }}</p>
          <p class="text-xs text-slate-500">Score out of 100</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <StatusBadge :status="status" />
        <svg :class="open ? 'rotate-180' : ''" class="w-5 h-5 text-slate-500 transition-transform" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
      </div>
    </button>
    <div v-if="open" class="px-6 pb-6 pt-4 border-t border-slate-700/50">
      <p v-if="rationale" class="text-sm text-slate-300 bg-slate-800/60 rounded-xl px-4 py-3 mb-4 border border-slate-700/40 leading-relaxed">
        {{ rationale }}
      </p>
      <slot name="content" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const StatusBadge = {
  props: ['status'],
  template: `<span :class="cls" class="text-xs font-bold px-2 py-0.5 rounded-md">{{ label }}</span>`,
  computed: {
    cls() {
      return {
        pass:        'bg-green-500/20  text-green-300',
        warn:        'bg-yellow-500/20 text-yellow-300',
        fail:        'bg-red-500/20    text-red-300',
        pending:     'bg-slate-700     text-slate-300',
        unavailable: 'bg-slate-700     text-slate-400',
      }[this.status] ?? 'bg-slate-700 text-slate-300'
    },
    label() {
      return { pass: 'PASS', warn: 'WARN', fail: 'FAIL', pending: 'PENDING', unavailable: 'N/A' }[this.status] ?? this.status?.toUpperCase()
    },
  }
}

const props = defineProps({
  title:    String,
  score:    Number,
  status:   String,
  rationale: String,
})

const open = ref(false)

const scoreColor = computed(() => {
  if (props.score >= 80) return 'text-green-400'
  if (props.score >= 60) return 'text-blue-400'
  if (props.score >= 40) return 'text-yellow-400'
  return 'text-red-400'
})
</script>
