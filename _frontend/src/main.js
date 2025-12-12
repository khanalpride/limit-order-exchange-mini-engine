import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './assets/main.css'
import echo from './echo'

const app = createApp(App)

// Make Echo available globally
app.config.globalProperties.$echo = echo

app.use(router)

app.mount('#app')
