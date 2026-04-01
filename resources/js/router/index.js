import { createRouter, createWebHistory } from 'vue-router'
import LandingPage from '../views/LandingPage.vue'
import ResultsPage from '../views/ResultsPage.vue'

const routes = [
  { path: '/',            name: 'landing', component: LandingPage },
  { path: '/results/:id', name: 'results', component: ResultsPage, props: true },
]

export default createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})
