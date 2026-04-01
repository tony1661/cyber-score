<template>
  <div class="flex flex-col items-center">
    <svg viewBox="0 0 220 130" class="w-full max-w-[260px]" aria-label="Score gauge">
      <!-- Tick marks at 0, 25, 50, 75, 100 -->
      <g v-for="pct in [0, 25, 50, 75, 100]" :key="pct">
        <line
          :x1="tickInner(pct).x" :y1="tickInner(pct).y"
          :x2="tickOuter(pct).x" :y2="tickOuter(pct).y"
          stroke="#334155" stroke-width="2" stroke-linecap="round"
        />
        <text
          :x="tickLabel(pct).x" :y="tickLabel(pct).y"
          text-anchor="middle" dominant-baseline="middle"
          fill="#475569" font-size="9" font-family="sans-serif"
        >{{ pct }}</text>
      </g>

      <!-- Track -->
      <path d="M 10,120 A 100,100 0 0,1 210,120"
        fill="none" stroke="#1e293b" stroke-width="16" stroke-linecap="round"/>

      <!-- Filled arc -->
      <path d="M 10,120 A 100,100 0 0,1 210,120"
        fill="none" :stroke="color" stroke-width="16" stroke-linecap="round"
        :stroke-dasharray="`${(score / 100) * ARC_LEN} ${ARC_LEN}`"
        style="transition: stroke-dasharray 1s ease;"
      />

      <!-- Score number -->
      <text x="110" y="97" text-anchor="middle" dominant-baseline="middle"
        :fill="color" font-size="46" font-weight="800" font-family="sans-serif"
        letter-spacing="-1">{{ score }}</text>

      <!-- /100 -->
      <text x="110" y="119" text-anchor="middle"
        fill="#475569" font-size="12" font-family="sans-serif">/ 100</text>
    </svg>

    <!-- Grade label below gauge -->
    <p class="text-xl font-bold -mt-1" :style="{ color }">{{ grade }}</p>
  </div>
</template>

<script setup>
const ARC_LEN = Math.PI * 100  // semicircle r=100

const props = defineProps({
  score: { type: Number, default: 0 },
  color: { type: String, default: '#64748b' },
  grade: { type: String, default: '' },
})

// cx=110, cy=120, r=100 for the main arc
// Ticks: inner r=92, outer r=107, label r=116
function angleForPct(pct) {
  // 0% → 180° (left), 100% → 0° (right), in radians
  return Math.PI - (pct / 100) * Math.PI
}

function polar(r, angleDeg) {
  const a = Math.PI - (angleDeg / 100) * Math.PI
  return { x: 110 + r * Math.cos(a), y: 120 - r * Math.sin(a) }
}

function tickInner(pct)  { return polar(89,  pct) }
function tickOuter(pct)  { return polar(104, pct) }
function tickLabel(pct)  { return polar(114, pct) }
</script>
