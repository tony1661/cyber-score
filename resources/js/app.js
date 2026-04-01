import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router/index.js'
import axios from 'axios'

// Set CSRF token for all requests
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
const token = document.head.querySelector('meta[name="csrf-token"]')
if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content
}

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
