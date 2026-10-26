import axios from 'axios'
import { ElMessage } from 'element-plus'
import { reactive } from 'vue'

const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
})

// 統一顯示錯誤訊息；呼叫端可用 { silent: true } 自行處理
api.interceptors.response.use(
  (res) => res,
  async (error) => {
    if (!error.config?.silent) {
      let data = error.response?.data
      if (data instanceof Blob) {
        try {
          data = JSON.parse(await data.text())
        } catch {
          data = null
        }
      }
      const firstError = data?.errors && Object.values(data.errors)[0]?.[0]
      ElMessage.error(firstError || data?.message || error.message)
    }
    return Promise.reject(error)
  },
)

export default api

/** 下載檔案（保留後端的錯誤訊息處理） */
export async function download(url, params, fallbackName) {
  const res = await api.get(url, { params, responseType: 'blob' })
  const disposition = res.headers['content-disposition'] || ''
  const match = disposition.match(/filename\*=utf-8''([^;]+)/i) || disposition.match(/filename="?([^";]+)"?/)
  const name = match ? decodeURIComponent(match[1]) : fallbackName
  const link = document.createElement('a')
  link.href = URL.createObjectURL(res.data)
  link.download = name
  link.click()
  URL.revokeObjectURL(link.href)
}

/** 代碼表（/api/meta），全站共用一份 */
export const meta = reactive({ loaded: false })
let metaPromise = null
export function loadMeta() {
  metaPromise ??= api.get('/meta').then((res) => Object.assign(meta, res.data, { loaded: true }))
  return metaPromise
}

export function labelOf(list, value) {
  return list?.find((o) => o.value === value)?.label ?? value
}
