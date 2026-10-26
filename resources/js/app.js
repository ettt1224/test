import { createApp } from 'vue'
import ElementPlus from 'element-plus'
import zhTw from 'element-plus/es/locale/lang/zh-tw'
import * as Icons from '@element-plus/icons-vue'
import 'element-plus/dist/index.css'
import '../css/app.css'
import App from './App.vue'
import router from './router'

const app = createApp(App)
for (const [name, component] of Object.entries(Icons)) {
  app.component(name, component)
}
app.use(ElementPlus, { locale: zhTw })
app.use(router)
app.mount('#app')
