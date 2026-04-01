import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useAssessmentStore = defineStore('assessment', () => {
  const result      = ref(null)
  const loading     = ref(false)
  const error       = ref(null)
  const emailSent   = ref(false)
  const sendingEmail = ref(false)

  async function runAssessment(email, consentToEmail) {
    loading.value = true
    error.value   = null
    result.value  = null
    emailSent.value = false

    try {
      const { data } = await axios.post('/api/assessments', {
        email,
        consent_to_email: consentToEmail,
      })
      result.value = data
      return data
    } catch (err) {
      const msg = err.response?.data?.message
        || err.response?.data?.errors?.email?.[0]
        || 'Something went wrong. Please try again.'
      error.value = msg
      throw err
    } finally {
      loading.value = false
    }
  }

  async function sendEmailReport(submissionId) {
    sendingEmail.value = true
    try {
      await axios.post(`/api/assessments/${submissionId}/email-report`)
      emailSent.value = true
    } catch (err) {
      throw err
    } finally {
      sendingEmail.value = false
    }
  }

  function reset() {
    result.value      = null
    loading.value     = false
    error.value       = null
    emailSent.value   = false
    sendingEmail.value = false
  }

  return { result, loading, error, emailSent, sendingEmail, runAssessment, sendEmailReport, reset }
})
