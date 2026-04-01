<template>
  <div :class="chipClass" class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-semibold">
    <span>{{ label }}</span>
    <span :class="dotClass" class="w-2 h-2 rounded-full flex-shrink-0"></span>
    <span class="font-normal opacity-80">{{ detail }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label:  String,
  status: String,  // pass | warn | fail
  detail: String,
})

const chipClass = computed(() => ({
  pass: 'bg-green-500/10  border-green-500/30  text-green-300',
  warn: 'bg-yellow-500/10 border-yellow-500/30 text-yellow-300',
  fail: 'bg-red-500/10    border-red-500/30    text-red-300',
}[props.status] ?? 'bg-slate-700/50 border-slate-600 text-slate-300'))

const dotClass = computed(() => ({
  pass: 'bg-green-400',
  warn: 'bg-yellow-400',
  fail: 'bg-red-400',
}[props.status] ?? 'bg-slate-400'))
</script>
