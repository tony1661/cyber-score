<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-slate-100">

    <!-- Header -->
    <header class="px-6 py-5 flex items-center justify-between max-w-6xl mx-auto">
      <a href="/" class="flex items-center hover:opacity-80 transition">
        <img :src="logoUrl" alt="MeteorTel" class="h-8 w-auto" />
      </a>
      <button @click="goBack" class="text-slate-400 hover:text-white text-sm transition flex items-center gap-1.5">
        ← New Assessment
      </button>
    </header>

    <!-- Loading state -->
    <div v-if="loading" class="flex flex-col items-center justify-center min-h-[70vh] gap-6">
      <div class="w-16 h-16 border-4 border-blue-500/30 border-t-blue-500 rounded-full animate-spin"></div>
      <div class="text-center">
        <p class="text-white font-semibold text-lg">Running Assessment…</p>
        <p class="text-slate-400 text-sm mt-1">Checking breach databases and DNS configuration</p>
      </div>
    </div>

    <!-- Password gate (direct link only) -->
    <div v-else-if="showPasswordGate" class="flex flex-col items-center justify-center min-h-[70vh] gap-6 px-4">
      <div class="bg-slate-900/70 border border-slate-700/50 rounded-2xl p-8 w-full max-w-sm text-center shadow-2xl backdrop-blur-sm">
        <div class="text-4xl mb-4">🔒</div>
        <h2 class="text-white font-bold text-xl mb-2">Results Protected</h2>
        <p class="text-slate-400 text-sm mb-6">Enter the password to view this assessment.</p>
        <form @submit.prevent="submitPassword">
          <input
            v-model="passwordInput"
            type="password"
            placeholder="Password"
            autocomplete="current-password"
            class="w-full bg-slate-800 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 mb-3"
          />
          <p v-if="passwordError" class="text-red-400 text-sm mb-3">{{ passwordError }}</p>
          <button
            type="submit"
            :disabled="loading"
            class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold py-3 rounded-xl transition"
          >
            <span v-if="loading">Verifying…</span>
            <span v-else>View Results</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="!result" class="flex flex-col items-center justify-center min-h-[70vh] gap-4">
      <div class="text-6xl">⚠️</div>
      <p class="text-white font-semibold text-lg">Assessment unavailable</p>
      <p class="text-slate-400 text-sm">Could not load the results. Please try again.</p>
      <button @click="goBack" class="mt-4 px-6 py-2 bg-blue-600 rounded-xl text-white font-semibold hover:bg-blue-700 transition">
        Try Again
      </button>
    </div>

    <template v-else>
      <div class="max-w-5xl mx-auto px-4 pb-20">

        <!-- ── HERO SCORE CARD ── -->
        <section class="mt-8 mb-10">
          <div class="bg-slate-900/70 border border-slate-700/50 rounded-2xl p-6 shadow-2xl backdrop-blur-sm">
            <p class="text-slate-400 text-sm font-medium uppercase tracking-widest text-center mb-1">Email Exposure Score</p>
            <p class="text-slate-500 text-sm text-center mb-6">{{ result.email }}</p>

            <!-- Gauge + Radar side by side -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
              <div class="w-full sm:w-1/2 flex justify-center">
                <ScoreGauge
                  :score="result.overall_score"
                  :color="scoreColor"
                  :grade="result.grade"
                />
              </div>
              <div class="w-full sm:w-1/2 flex justify-center">
                <RadarChart :categories="result.categories" class="w-full max-w-xs" />
              </div>
            </div>

            <p class="text-slate-400 max-w-lg mx-auto text-sm leading-relaxed text-center mt-4">{{ result.summary }}</p>

            <!-- Risk + authentication pills -->
            <div class="mt-5 flex items-center justify-center gap-3 flex-wrap">
              <AuthChip
                v-for="pill in riskPills"
                :key="pill.label"
                :label="pill.label"
                :status="pill.status"
                :detail="pill.detail"
              />
              <span v-if="riskPills.length" class="w-1 h-1 rounded-full bg-slate-600"></span>
              <AuthChip label="SPF"   :status="dnsStatus('spf')"   :detail="result.dns_data?.spf?.quality || 'missing'" />
              <AuthChip label="DKIM"  :status="dnsStatus('dkim')"  :detail="result.dns_data?.dkim?.selector ? `selector: ${result.dns_data.dkim.selector}` : 'not detected'" />
              <AuthChip label="DMARC" :status="dnsStatus('dmarc')" :detail="result.dns_data?.dmarc?.policy ? `p=${result.dns_data.dmarc.policy}` : 'missing'" />
            </div>
          </div>
        </section>

        <!-- ── CATEGORY SCORE CARDS ── -->
        <section class="mb-10">
          <h2 class="text-lg font-bold text-white mb-5">Category Scores</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <CategoryCard
              v-for="(cat, key) in result.categories"
              :key="key"
              :name="cat.name"
              :score="cat.score"
              :status="cat.status"
              :rationale="cat.rationale"
            />
          </div>
        </section>

        <!-- ── CHARTS ROW ── -->
        <section class="mb-10">
          <h2 class="text-lg font-bold text-white mb-5">Visualizations</h2>
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-slate-900/70 border border-slate-700/50 rounded-2xl p-6 backdrop-blur-sm">
              <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-widest mb-4">Breach Timeline</h3>
              <BreachTimeline :breaches="result.breach_data?.breaches || []" />
            </div>
            <div class="bg-slate-900/70 border border-slate-700/50 rounded-2xl p-6 backdrop-blur-sm">
              <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-widest mb-4">Exposed Data Types</h3>
              <DataComposition :attributes="result.breach_data?.exposed_attributes || []" />
            </div>
          </div>
          <div class="mt-6 bg-slate-900/70 border border-slate-700/50 rounded-2xl p-6 backdrop-blur-sm">
            <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-widest mb-4">Category Score Comparison</h3>
            <CategoryComparison :categories="result.categories" />
          </div>
        </section>

        <!-- ── DETAILED CATEGORY SECTIONS ── -->
        <section class="mb-10 space-y-5">
          <h2 class="text-lg font-bold text-white mb-5">Detailed Findings</h2>

          <!-- Breach History detail -->
          <DetailSection title="Breach History" :score="result.categories.breach_history?.score" :status="result.categories.breach_history?.status" :rationale="result.categories.breach_history?.rationale">
            <template #content>
              <div v-if="!result.breach_data?.found" class="flex items-center gap-3 text-green-400 font-semibold">
                <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                No known breaches found for this email address.
              </div>
              <template v-else>
                <p class="text-slate-400 text-sm mb-4">{{ result.breach_data.breach_count }} breach(es) found involving this email address.</p>
                <div class="space-y-3">
                  <div v-for="breach in result.breach_data.breaches" :key="breach.source_name"
                    class="bg-red-500/5 border border-red-500/20 rounded-xl p-4">
                    <div class="flex items-start justify-between gap-4">
                      <div>
                        <p class="font-semibold text-red-300 text-sm">{{ breach.source_name }}</p>
                        <p v-if="breach.breach_date" class="text-slate-500 text-xs mt-0.5">{{ formatDate(breach.breach_date) }}</p>
                      </div>
                      <span class="text-xs bg-red-500/20 text-red-300 px-2 py-1 rounded-lg font-medium whitespace-nowrap">
                        {{ breach.record_count?.toLocaleString() || '?' }} records
                      </span>
                    </div>
                    <div v-if="breach.exposed_attributes?.length" class="mt-2 flex flex-wrap gap-1.5">
                      <span v-for="attr in breach.exposed_attributes" :key="attr"
                        class="text-xs bg-slate-800 text-slate-300 px-2 py-0.5 rounded-md">
                        {{ attr }}
                      </span>
                    </div>
                  </div>
                </div>
              </template>
              <div class="mt-4 p-4 bg-blue-500/5 border border-blue-500/20 rounded-xl text-sm text-slate-400">
                <strong class="text-blue-300">Remediation:</strong>
                Change passwords for any accounts associated with this email, especially if password data was exposed.
                Enable multi-factor authentication (MFA) wherever possible.
              </div>
            </template>
          </DetailSection>

          <!-- Data Sensitivity detail -->
          <DetailSection title="Data Sensitivity Exposed" :score="result.categories.data_sensitivity?.score" :status="result.categories.data_sensitivity?.status" :rationale="result.categories.data_sensitivity?.rationale">
            <template #content>
              <div v-if="!result.breach_data?.exposed_attributes?.length" class="text-green-400 font-semibold text-sm flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                No sensitive data types detected in known exposures.
              </div>
              <template v-else>
                <p class="text-slate-400 text-sm mb-3">The following data types were found in breach records:</p>
                <div class="flex flex-wrap gap-2 mb-4">
                  <span v-for="attr in result.breach_data.exposed_attributes" :key="attr"
                    :class="sensitivityClass(attr)"
                    class="text-xs font-semibold px-3 py-1 rounded-full">
                    {{ attr }}
                  </span>
                </div>
              </template>
              <div class="p-4 bg-blue-500/5 border border-blue-500/20 rounded-xl text-sm text-slate-400">
                <strong class="text-blue-300">Remediation:</strong>
                Password exposure is the highest risk — update all passwords immediately. Consider a password manager.
                For identity data exposure (name, DOB, SSN), monitor credit reports for signs of identity fraud.
              </div>
            </template>
          </DetailSection>

          <!-- SPF detail -->
          <DetailSection title="SPF Health" :score="result.categories.spf_health?.score" :status="result.categories.spf_health?.status" :rationale="result.categories.spf_health?.rationale">
            <template #content>
              <div class="text-sm text-slate-400 space-y-3">
                <p v-if="result.dns_data?.spf?.raw" class="font-mono text-xs bg-slate-800 rounded-lg p-3 text-cyan-300 break-all">
                  {{ result.dns_data.spf.raw }}
                </p>
                <p v-else class="text-red-400">No SPF record found on this domain.</p>
                <p>{{ result.categories.spf_health?.rationale }}</p>
                <div class="p-4 bg-blue-500/5 border border-blue-500/20 rounded-xl">
                  <strong class="text-blue-300">Remediation:</strong>
                  Add a TXT record at your DNS provider. Example of a strict policy:
                  <code class="block mt-2 font-mono text-xs bg-slate-800 rounded p-2 text-cyan-300">v=spf1 include:_spf.google.com -all</code>
                </div>
              </div>
            </template>
          </DetailSection>

          <!-- DKIM detail -->
          <DetailSection title="DKIM Health" :score="result.categories.dkim_health?.score" :status="result.categories.dkim_health?.status" :rationale="result.categories.dkim_health?.rationale">
            <template #content>
              <div class="text-sm text-slate-400 space-y-3">
                <p v-if="result.dns_data?.dkim?.raw" class="font-mono text-xs bg-slate-800 rounded-lg p-3 text-cyan-300 break-all">
                  {{ result.dns_data.dkim.raw?.substring(0, 120) }}{{ result.dns_data.dkim.raw?.length > 120 ? '…' : '' }}
                </p>
                <p v-else class="text-red-400">No DKIM selector detected across common selector names.</p>
                <p>{{ result.categories.dkim_health?.rationale }}</p>
                <div class="p-4 bg-blue-500/5 border border-blue-500/20 rounded-xl">
                  <strong class="text-blue-300">Remediation:</strong>
                  Enable DKIM signing through your email provider (Google Workspace, Microsoft 365, etc.) and publish the provided TXT record at <code class="text-cyan-300">selector._domainkey.yourdomain.com</code>.
                </div>
              </div>
            </template>
          </DetailSection>

          <!-- DMARC detail -->
          <DetailSection title="DMARC Enforcement" :score="result.categories.dmarc_enforcement?.score" :status="result.categories.dmarc_enforcement?.status" :rationale="result.categories.dmarc_enforcement?.rationale">
            <template #content>
              <div class="text-sm text-slate-400 space-y-3">
                <p v-if="result.dns_data?.dmarc?.raw" class="font-mono text-xs bg-slate-800 rounded-lg p-3 text-cyan-300 break-all">
                  {{ result.dns_data.dmarc.raw }}
                </p>
                <p v-else class="text-red-400">No DMARC record found at _dmarc.{{ result.domain }}.</p>
                <p>{{ result.categories.dmarc_enforcement?.rationale }}</p>
                <div class="p-4 bg-blue-500/5 border border-blue-500/20 rounded-xl">
                  <strong class="text-blue-300">Remediation:</strong>
                  Start with p=none to collect reports, then progress to p=quarantine, then p=reject.
                  <code class="block mt-2 font-mono text-xs bg-slate-800 rounded p-2 text-cyan-300">v=DMARC1; p=reject; rua=mailto:dmarc@{{ result.domain }}; ruf=mailto:dmarc@{{ result.domain }}; pct=100</code>
                </div>
              </div>
            </template>
          </DetailSection>

          <!-- Domain Posture detail -->
          <DetailSection title="Domain Security Posture" :score="result.categories.domain_posture?.score" :status="result.categories.domain_posture?.status" :rationale="result.categories.domain_posture?.rationale">
            <template #content>
              <div class="text-sm text-slate-400 space-y-4">

                <!-- Auth summary grid -->
                <div class="grid grid-cols-3 gap-3">
                  <div v-for="(item, i) in authSummary" :key="i"
                    :class="item.ok ? 'border-green-500/30 bg-green-500/5' : 'border-red-500/30 bg-red-500/5'"
                    class="border rounded-xl p-3 text-center">
                    <p :class="item.ok ? 'text-green-400' : 'text-red-400'" class="font-bold text-lg">{{ item.label }}</p>
                    <p :class="item.ok ? 'text-green-400' : 'text-red-400'" class="text-xs font-semibold mt-0.5">{{ item.ok ? '✓ OK' : '✗ Issue' }}</p>
                    <p class="text-slate-500 text-xs mt-1">{{ item.detail }}</p>
                  </div>
                </div>

                <!-- Domain breach leaderboard -->
                <div class="mt-2">
                  <h4 class="text-slate-300 font-semibold text-sm mb-3">
                    Domain Breach Exposure — {{ result.domain }}
                  </h4>

                  <!-- Pending -->
                  <div v-if="result.domain_breach_data?.pending"
                    class="bg-yellow-500/5 border border-yellow-500/20 rounded-xl p-4 text-yellow-300 text-sm space-y-1">
                    <p class="font-semibold">⏳ Domain analysis in progress</p>
                    <p class="text-yellow-400/80">
                      XposedOrNot is compiling breach data for <strong>{{ result.domain }}</strong>.
                      This is a one-time indexing step that typically completes within a few hours.
                      Run a new assessment later to see the full domain leaderboard.
                    </p>
                  </div>

                  <!-- Quota exceeded — skip silently with a neutral note -->
                  <div v-else-if="result.domain_breach_data?.quota_exceeded"
                    class="bg-slate-800/50 border border-slate-600/30 rounded-xl p-4 text-slate-500 text-sm">
                    Domain-level breach analysis is not available for this assessment.
                  </div>

                  <!-- Unavailable -->
                  <div v-else-if="!result.domain_breach_data?.available"
                    class="bg-slate-800/50 border border-slate-600/30 rounded-xl p-4 text-slate-500 text-sm">
                    Domain breach data unavailable.
                  </div>

                  <!-- No breaches -->
                  <div v-else-if="!result.domain_breach_data?.found"
                    class="bg-green-500/5 border border-green-500/20 rounded-xl p-4 text-green-400 font-semibold text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    No known breaches found for any accounts on {{ result.domain }}.
                  </div>

                  <!-- Leaderboard -->
                  <template v-else>
                    <div class="flex items-center justify-between mb-3 text-xs text-slate-500">
                      <span>{{ result.domain_breach_data.breach_count }} breach(es) affecting this domain</span>
                      <span>{{ result.domain_breach_data.total_exposed?.toLocaleString() }} account(s) exposed total</span>
                    </div>
                    <div class="rounded-xl overflow-hidden border border-slate-700/50">
                      <table class="w-full text-sm">
                        <thead>
                          <tr class="bg-slate-800/80 text-slate-400 text-xs uppercase tracking-wider">
                            <th class="text-left px-4 py-2.5">#</th>
                            <th class="text-left px-4 py-2.5">Breach</th>
                            <th class="text-left px-4 py-2.5 hidden sm:table-cell">Date</th>
                            <th class="text-left px-4 py-2.5 hidden sm:table-cell">Data Exposed</th>
                            <th class="text-right px-4 py-2.5">Accounts</th>
                          </tr>
                        </thead>
                        <tbody>
                          <template v-for="(b, idx) in result.domain_breach_data.top_breaches" :key="b.breach">
                            <tr
                              :class="idx % 2 === 0 ? 'bg-slate-900/40' : 'bg-slate-800/20'"
                              class="border-t border-slate-700/30">
                              <td class="px-4 py-2.5 text-slate-500 font-mono text-xs align-top">{{ idx + 1 }}</td>
                              <td class="px-4 py-2.5 align-top">
                                <div class="text-white font-semibold mb-1.5">{{ b.breach }}</div>
                                <div v-if="b.emails?.length" class="flex flex-wrap gap-1">
                                  <span v-for="email in b.emails" :key="email"
                                    class="text-xs font-mono bg-red-950/60 border border-red-800/40 text-red-300 px-2 py-0.5 rounded">
                                    {{ email }}
                                  </span>
                                </div>
                              </td>
                              <td class="px-4 py-2.5 text-slate-400 hidden sm:table-cell align-top">
                                {{ b.breach_date ? b.breach_date.substring(0,7) : '—' }}
                              </td>
                              <td class="px-4 py-2.5 hidden sm:table-cell align-top">
                                <div class="flex flex-wrap gap-1">
                                  <span v-for="attr in sortedAttrs(b.xposed_data).slice(0,3)" :key="attr"
                                    :class="isPasswordAttr(attr) ? 'bg-red-950/60 border border-red-800/40 text-red-300' : 'bg-slate-700 text-slate-300'"
                                    class="text-xs px-1.5 py-0.5 rounded">{{ attr }}</span>
                                  <span v-if="(b.xposed_data || []).length > 3" class="text-xs text-slate-500">+{{ b.xposed_data.length - 3 }}</span>
                                </div>
                              </td>
                              <td class="px-4 py-2.5 text-right font-bold align-top"
                                :class="b.email_count >= 10 ? 'text-red-400' : b.email_count >= 3 ? 'text-yellow-400' : 'text-slate-300'">
                                {{ b.email_count.toLocaleString() }}
                              </td>
                            </tr>
                          </template>
                        </tbody>
                      </table>
                    </div>
                  </template>
                </div>

              </div>
            </template>
          </DetailSection>
        </section>

        <!-- ── EMAIL CTA ── -->
        <section class="mb-10">
          <div class="bg-gradient-to-r from-blue-900/40 to-cyan-900/40 border border-blue-500/30 rounded-2xl p-8 text-center backdrop-blur-sm">
            <h2 class="text-xl font-bold text-white mb-2">Receive This Report by Email</h2>
            <p class="text-slate-400 text-sm mb-6 max-w-md mx-auto">
              Get a full copy of this assessment sent to <strong class="text-white">{{ result.email }}</strong>.
              A security specialist will also be copied for follow-up support.
            </p>

            <div v-if="emailSentSuccess" class="flex items-center justify-center gap-2 text-green-400 font-semibold">
              <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              Report sent! Check your inbox.
            </div>
            <div v-else-if="emailError" class="text-red-400 text-sm mb-4">{{ emailError }}</div>

            <button
              v-if="!emailSentSuccess"
              @click="handleSendEmail"
              :disabled="sendingEmail"
              class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold px-8 py-3 rounded-xl transition-all shadow-lg shadow-blue-500/20"
            >
              <span v-if="sendingEmail" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                Sending…
              </span>
              <span v-else>📧 Send Me This Report</span>
            </button>

            <p class="mt-4 text-slate-600 text-xs">
              By clicking send, you consent to receiving this report by email. A MeteorTel security specialist may follow up to discuss your results.
            </p>
          </div>
        </section>

        <!-- ── SALES FOOTER ── -->
        <section class="text-center py-8 border-t border-slate-800">
          <p class="text-slate-400 mb-4">Want expert help fixing these issues?</p>
          <a :href="discoveryCallUrl" target="_blank" rel="noopener"
            class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white font-semibold px-6 py-3 rounded-xl transition">
            📞 Book a Security Review with MeteorTel
          </a>
        </section>

      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useAssessmentStore } from '../stores/assessment.js'
import CategoryCard from '../components/CategoryCard.vue'
import ScoreGauge from '../components/ScoreGauge.vue'
import RadarChart from '../components/charts/RadarChart.vue'
import BreachTimeline from '../components/charts/BreachTimeline.vue'
import DataComposition from '../components/charts/DataComposition.vue'
import CategoryComparison from '../components/charts/CategoryComparison.vue'
import AuthChip from '../components/AuthChip.vue'
import DetailSection from '../components/DetailSection.vue'

const props  = defineProps({ id: String })
const router = useRouter()
const store  = useAssessmentStore()

const loading          = ref(false)
const emailSentSuccess = ref(false)
const emailError       = ref(null)
const sendingEmail     = ref(false)
const showPasswordGate = ref(false)
const passwordInput    = ref('')
const passwordError    = ref(null)

const PASSWORD_ATTRS = ['passwords', 'password']
const SENSITIVE_ATTRS = ['credit cards', 'social security numbers', 'ssn', 'bank account numbers', 'passport numbers', 'health records', 'medical records']

function isPasswordAttr(attr) {
  return PASSWORD_ATTRS.includes(attr?.toLowerCase())
}

function sortedAttrs(attrs) {
  if (!attrs?.length) return []
  return [...attrs].sort((a, b) => {
    const al = a.toLowerCase(), bl = b.toLowerCase()
    const aPass = PASSWORD_ATTRS.includes(al) ? 0 : SENSITIVE_ATTRS.includes(al) ? 1 : 2
    const bPass = PASSWORD_ATTRS.includes(bl) ? 0 : SENSITIVE_ATTRS.includes(bl) ? 1 : 2
    return aPass - bPass
  })
}

const result = computed(() => store.result)

async function fetchResult(password = null) {
  loading.value = true
  passwordError.value = null
  try {
    const headers = password ? { 'X-Results-Password': password } : {}
    const { data } = await axios.get(`/api/assessments/${props.id}`, { headers })
    store.result = data
    showPasswordGate.value = false
  } catch (err) {
    if (err.response?.status === 401) {
      showPasswordGate.value = true
    } else {
      router.push({ name: 'landing' })
    }
  } finally {
    loading.value = false
  }
}

async function submitPassword() {
  if (!passwordInput.value) return
  await fetchResult(passwordInput.value)
  if (showPasswordGate.value) {
    passwordError.value = 'Incorrect password. Please try again.'
    passwordInput.value = ''
  }
}

onMounted(async () => {
  if (!store.result) {
    if (props.id) {
      await fetchResult()
    } else {
      router.push({ name: 'landing' })
    }
  }
})

const scoreColor = computed(() => {
  const s = result.value?.overall_score ?? 0
  if (s >= 90) return '#10b981'
  if (s >= 75) return '#3b82f6'
  if (s >= 55) return '#f59e0b'
  if (s >= 35) return '#ef4444'
  return '#dc2626'
})

const riskPills = computed(() => {
  const pills = []
  const bd = result.value?.breach_data
  if (!bd) return pills

  if (!bd.available) {
    pills.push({ label: 'Breach Check', status: 'warn', detail: 'unavailable' })
    return pills
  }

  if (!bd.found) {
    pills.push({ label: 'Breaches', status: 'pass', detail: 'none found' })
  } else {
    pills.push({ label: 'Breaches', status: 'fail', detail: `${bd.breach_count} found` })
  }

  const attrs = bd.exposed_attributes ?? []
  const hasPassword = attrs.some(a => a.includes('password'))
  if (hasPassword) {
    pills.push({ label: 'Passwords', status: 'fail', detail: 'exposed' })
  }

  const hasSensitive = attrs.some(a =>
    ['date', 'dob', 'phone', 'address', 'social', 'ssn', 'credit', 'bank'].some(k => a.includes(k))
  )
  if (hasSensitive && !hasPassword) {
    pills.push({ label: 'Sensitive Data', status: 'fail', detail: 'exposed' })
  }

  return pills
})

const authSummary = computed(() => {
  const dns = result.value?.dns_data
  if (!dns) return []
  return [
    {
      label: 'SPF',
      ok: dns.spf?.found,
      detail: dns.spf?.quality || 'not found',
    },
    {
      label: 'DKIM',
      ok: dns.dkim?.found,
      detail: dns.dkim?.selector ? `selector: ${dns.dkim.selector}` : 'not found',
    },
    {
      label: 'DMARC',
      ok: dns.dmarc?.found,
      detail: dns.dmarc?.policy ? `p=${dns.dmarc.policy}` : 'not found',
    },
  ]
})

function dnsStatus(type) {
  const data = result.value?.dns_data?.[type]
  if (!data?.found) return 'fail'
  if (type === 'dmarc' && data.policy === 'reject') return 'pass'
  if (type === 'dmarc' && data.policy === 'quarantine') return 'warn'
  if (type === 'dmarc') return 'warn'
  if (type === 'spf' && data.quality === 'strict') return 'pass'
  if (type === 'spf' && data.quality === 'softfail') return 'pass'
  if (type === 'spf') return 'fail'
  if (type === 'dkim' && data.quality === 'valid') return 'pass'
  if (type === 'dkim' && data.quality === 'partial') return 'warn'
  return 'fail'
}

function sensitivityClass(attr) {
  const high = ['password', 'ssn', 'social security', 'credit card', 'bank']
  const med  = ['date of birth', 'dob', 'phone', 'address']
  const lower = attr.toLowerCase()
  if (high.some(h => lower.includes(h))) return 'bg-red-500/20 text-red-300'
  if (med.some(m => lower.includes(m)))  return 'bg-orange-500/20 text-orange-300'
  return 'bg-slate-700 text-slate-300'
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long' })
}

async function handleSendEmail() {
  sendingEmail.value = true
  emailError.value   = null
  try {
    await store.sendEmailReport(result.value.id)
    emailSentSuccess.value = true
  } catch (err) {
    emailError.value = err.response?.data?.message || 'Failed to send report. Please try again.'
  } finally {
    sendingEmail.value = false
  }
}

const logoUrl = '/meteor-logo.svg'
const discoveryCallUrl = window.appConfig?.discoveryCallUrl || 'https://meteortel.com/discovery-call/'

function goBack() {
  store.reset()
  router.push({ name: 'landing' })
}
</script>
