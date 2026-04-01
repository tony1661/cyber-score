<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 flex flex-col">

    <!-- Header -->
    <header class="px-6 py-5 flex items-center justify-between max-w-6xl mx-auto w-full">
      <div class="flex items-center">
        <img :src="logoUrl" alt="MeteorTel" class="h-8 w-auto" />
      </div>
    </header>

    <!-- Hero -->
    <main class="flex-1 flex items-center justify-center px-4 py-16">
      <div class="max-w-2xl w-full text-center">

        <div class="inline-flex items-center gap-2 bg-blue-500/10 border border-blue-500/20 rounded-full px-4 py-1.5 text-blue-300 text-sm mb-8">
          <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
          Free Instant Assessment
        </div>

        <h1 class="text-4xl md:text-5xl font-extrabold text-white leading-tight mb-4">
          Is Your Business<br />
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-400">Exposed?</span>
        </h1>

        <p class="text-slate-400 text-lg mb-10 max-w-xl mx-auto">
          Enter your email address to instantly check breach exposure history
          and evaluate your domain's SPF, DKIM, and DMARC security configuration.
        </p>

        <!-- Assessment form -->
        <form @submit.prevent="handleSubmit" class="bg-slate-900/60 border border-slate-700/50 rounded-2xl p-8 backdrop-blur-sm text-left shadow-2xl">
          <div class="mb-5">
            <label class="block text-sm font-semibold text-slate-300 mb-2" for="email">
              Email Address
            </label>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              placeholder="you@yourdomain.com"
              required
              class="w-full bg-slate-800 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition text-base"
            />
          </div>

          <!-- Consent -->
          <label class="flex items-start gap-3 cursor-pointer mb-6">
            <div class="relative flex-shrink-0 mt-0.5">
              <input v-model="consent" type="checkbox" class="sr-only peer" required />
              <div class="w-5 h-5 rounded border-2 border-slate-600 peer-checked:bg-blue-500 peer-checked:border-blue-500 transition flex items-center justify-center">
                <svg v-if="consent" class="w-3 h-3 text-white" viewBox="0 0 12 12" fill="none">
                  <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
            </div>
            <span class="text-sm text-slate-400 leading-relaxed">
              I understand that my email address will be processed to perform this security assessment and may be stored for follow-up reporting purposes.
              I consent to receiving the assessment report by email.
            </span>
          </label>

          <div v-if="store.error" class="mb-5 bg-red-500/10 border border-red-500/30 rounded-xl px-4 py-3 text-red-400 text-sm">
            {{ store.error }}
          </div>

          <button
            type="submit"
            :disabled="store.loading || !consent"
            class="w-full bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-blue-500/20 text-base"
          >
            <span v-if="store.loading" class="flex items-center justify-center gap-2">
              <svg class="animate-spin w-5 h-5" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              Running Assessment…
            </span>
            <span v-else>Run Assessment →</span>
          </button>
        </form>

        <!-- Trust indicators -->
        <div class="mt-8 flex items-center justify-center gap-6 text-slate-500 text-sm flex-wrap">
          <span class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            Breach database check
          </span>
          <span class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            SPF / DKIM / DMARC analysis
          </span>
          <span class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            Weighted risk score
          </span>
        </div>

      </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 text-center text-slate-600 text-sm">
      © {{ new Date().getFullYear() }} MeteorTel Security. All rights reserved.
    </footer>

  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAssessmentStore } from '../stores/assessment.js'

const logoUrl = '/meteor-logo.svg'
const router = useRouter()
const store  = useAssessmentStore()

const email   = ref('')
const consent = ref(false)

async function handleSubmit() {
  if (!consent.value) return
  store.reset()
  try {
    const data = await store.runAssessment(email.value, consent.value)
    router.push({ name: 'results', params: { id: data.id }, state: { result: data } })
  } catch {
    // error is shown in the form via store.error
  }
}
</script>
