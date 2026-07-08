import '@fontsource/inter'
import '../css/app.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin } from '@tanstack/vue-query'
import App from './App.vue'
import router from './router'

createApp(App).use(createPinia()).use(VueQueryPlugin).use(router).mount('#app')
