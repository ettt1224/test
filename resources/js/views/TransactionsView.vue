<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api, { loadMeta, meta } from '../api'

const filters = reactive({ type: '', item_id: null, location_id: null, range: null, keyword: '' })
const page = reactive({ current: 1, size: 20, total: 0 })
const rows = ref([])
const loading = ref(false)

const items = ref([])
const locations = ref([])
const locationTree = ref([])
const storageLocations = computed(() => locations.value.filter((l) => l.level === 'location'))

const typeTag = { in: 'success', use: 'warning', split: '', transfer: 'info', out: 'danger' }

async function loadOptions() {
  const [i, l, t] = await Promise.all([api.get('/items/options'), api.get('/locations'), api.get('/locations/tree')])
  items.value = i.data
  locations.value = l.data
  locationTree.value = t.data
}

async function load() {
  loading.value = true
  try {
    const { range, ...rest } = filters
    const params = { ...rest, start: range?.[0], end: range?.[1], page: page.current, per_page: page.size }
    const { data } = await api.get('/transactions', { params })
    rows.value = data.data
    page.total = data.total
  } finally {
    loading.value = false
  }
}

function search() {
  page.current = 1
  load()
}

// ---- 新增作業 ----
const dialog = reactive({ visible: false, saving: false })
const formRef = ref()
const form = reactive({})
const fromStock = ref(null)
const currentType = computed(() => meta.transaction_types?.find((t) => t.value === form.type))
const rules = {
  type: [{ required: true, message: '請選擇作業類型' }],
  item_id: [{ required: true, message: '請選擇物品' }],
  quantity: [{ required: true, message: '請輸入數量' }],
  unit: [{ required: true, message: '請輸入單位' }],
  operator: [{ required: true, message: '請輸入操作人員' }],
  occurred_at: [{ required: true, message: '請選擇作業時間' }],
  from_location_id: [{ validator: (_, v, cb) => (currentType.value?.requires_from && !v ? cb(new Error('請選擇來源儲位')) : cb()) }],
  to_location_id: [{ validator: (_, v, cb) => (currentType.value?.requires_to && !v ? cb(new Error('請選擇目的儲位')) : cb()) }],
}

function now() {
  const d = new Date()
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`
}

function openCreate(type = 'in') {
  Object.assign(form, {
    type,
    item_id: null,
    quantity: null,
    unit: '',
    from_location_id: null,
    to_location_id: null,
    operator: localStorage.getItem('operator') || '',
    occurred_at: now(),
    batch_no: '',
    remark: '',
  })
  fromStock.value = null
  dialog.visible = true
  formRef.value?.clearValidate()
}

watch(
  () => form.item_id,
  (id) => {
    const item = items.value.find((i) => i.id === id)
    if (item?.default_unit && !form.unit) form.unit = item.default_unit
  },
)

// 顯示來源儲位現有庫存，避免超扣
watch(
  () => [form.item_id, form.from_location_id, form.unit, dialog.visible],
  async ([itemId, locationId, unit]) => {
    fromStock.value = null
    if (!itemId || !locationId) return
    const { data } = await api.get('/stocks', { params: { item_id: itemId, location_id: locationId } })
    fromStock.value = data.filter((s) => s.location_id === locationId && (!unit || s.unit === unit))
  },
)

async function save() {
  await formRef.value.validate()
  dialog.saving = true
  try {
    const { data } = await api.post('/transactions', form)
    localStorage.setItem('operator', form.operator)
    ElMessage.success(`已建立 ${data.tx_no}`)
    dialog.visible = false
    load()
  } finally {
    dialog.saving = false
  }
}

async function remove(row) {
  await ElMessageBox.confirm(`刪除「${row.tx_no}」並回沖庫存？`, '刪除確認', { type: 'warning' })
  await api.delete(`/transactions/${row.id}`)
  ElMessage.success('已刪除並回沖庫存')
  load()
}

onMounted(async () => {
  await Promise.all([loadMeta(), loadOptions()])
  load()
})
</script>

<template>
  <div class="page-toolbar">
    <el-select v-model="filters.type" placeholder="作業類型" clearable style="width: 110px" @change="search">
      <el-option v-for="o in meta.transaction_types" :key="o.value" :label="o.label" :value="o.value" />
    </el-select>
    <el-select v-model="filters.item_id" placeholder="物品" clearable filterable style="width: 200px" @change="search">
      <el-option v-for="i in items" :key="i.id" :label="`${i.code} ${i.name_zh}`" :value="i.id" />
    </el-select>
    <el-tree-select
      v-model="filters.location_id"
      :data="locationTree"
      :props="{ label: 'name', children: 'children' }"
      node-key="id"
      check-strictly
      clearable
      placeholder="儲位（含下層）"
      style="width: 200px"
      @change="search"
    />
    <el-date-picker v-model="filters.range" type="daterange" value-format="YYYY-MM-DD" start-placeholder="起" end-placeholder="迄" style="width: 240px" @change="search" />
    <el-input v-model="filters.keyword" placeholder="單號 / 批號 / 人員" clearable style="width: 170px" @keyup.enter="search" @clear="search" />
    <span class="spacer" />
    <el-dropdown split-button type="primary" @click="openCreate('in')" @command="openCreate">
      新增入庫
      <template #dropdown>
        <el-dropdown-menu>
          <el-dropdown-item v-for="o in meta.transaction_types" :key="o.value" :command="o.value">新增{{ o.label }}</el-dropdown-item>
        </el-dropdown-menu>
      </template>
    </el-dropdown>
  </div>

  <el-table v-loading="loading" :data="rows" border stripe>
    <el-table-column prop="tx_no" label="異動單號" width="150" />
    <el-table-column label="類型" width="70">
      <template #default="{ row }"><el-tag :type="typeTag[row.type]" size="small">{{ row.type_label }}</el-tag></template>
    </el-table-column>
    <el-table-column prop="occurred_at" label="作業時間" width="150">
      <template #default="{ row }">{{ row.occurred_at.slice(0, 16) }}</template>
    </el-table-column>
    <el-table-column label="物品" min-width="150">
      <template #default="{ row }">{{ row.item.code }} {{ row.item.name_zh }}</template>
    </el-table-column>
    <el-table-column label="數量" width="110" align="right">
      <template #default="{ row }"><span class="num">{{ row.quantity }} {{ row.unit }}</span></template>
    </el-table-column>
    <el-table-column prop="from_location_path" label="來源儲位" min-width="180" />
    <el-table-column prop="to_location_path" label="目的儲位" min-width="180" />
    <el-table-column prop="operator" label="操作人員" width="100" />
    <el-table-column prop="batch_no" label="批號" width="100" />
    <el-table-column prop="remark" label="備註" min-width="120" show-overflow-tooltip />
    <el-table-column label="操作" width="70" fixed="right">
      <template #default="{ row }">
        <el-button link type="danger" @click="remove(row)">刪除</el-button>
      </template>
    </el-table-column>
  </el-table>
  <el-pagination
    v-model:current-page="page.current"
    v-model:page-size="page.size"
    class="pager"
    :total="page.total"
    :page-sizes="[20, 50, 100]"
    layout="total, sizes, prev, pager, next"
    @current-change="load"
    @size-change="search"
  />

  <el-dialog v-model="dialog.visible" :title="`新增${currentType?.label ?? ''}作業`" width="560px">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
      <el-form-item label="作業類型" prop="type">
        <el-radio-group v-model="form.type">
          <el-radio-button v-for="o in meta.transaction_types" :key="o.value" :value="o.value">{{ o.label }}</el-radio-button>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="物品" prop="item_id">
        <el-select v-model="form.item_id" filterable placeholder="搜尋代號或名稱">
          <el-option v-for="i in items" :key="i.id" :label="`${i.code} ${i.name_zh}`" :value="i.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="數量" prop="quantity">
        <el-input-number v-model="form.quantity" :min="0.001" :precision="3" controls-position="right" />
        <el-select v-model="form.unit" filterable allow-create placeholder="單位" style="width: 110px; margin-left: 8px">
          <el-option v-for="u in meta.units" :key="u" :label="u" :value="u" />
        </el-select>
      </el-form-item>
      <el-form-item v-if="currentType?.requires_from" label="來源儲位" prop="from_location_id">
        <el-select v-model="form.from_location_id" filterable placeholder="選擇儲位">
          <el-option v-for="l in storageLocations" :key="l.id" :label="l.path" :value="l.id" />
        </el-select>
        <div v-if="fromStock" class="stock-hint">
          現有庫存：
          <template v-if="fromStock.length">
            <span v-for="s in fromStock" :key="s.id">{{ s.quantity }} {{ s.unit }}　</span>
          </template>
          <span v-else>0</span>
        </div>
      </el-form-item>
      <el-form-item v-if="currentType?.requires_to" label="目的儲位" prop="to_location_id">
        <el-select v-model="form.to_location_id" filterable placeholder="選擇儲位">
          <el-option v-for="l in storageLocations" :key="l.id" :label="l.path" :value="l.id" :disabled="l.id === form.from_location_id" />
        </el-select>
      </el-form-item>
      <el-form-item label="操作人員" prop="operator"><el-input v-model="form.operator" /></el-form-item>
      <el-form-item label="作業時間" prop="occurred_at">
        <el-date-picker v-model="form.occurred_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" format="YYYY-MM-DD HH:mm" />
      </el-form-item>
      <el-form-item label="批號"><el-input v-model="form.batch_no" /></el-form-item>
      <el-form-item label="備註"><el-input v-model="form.remark" type="textarea" /></el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="dialog.visible = false">取消</el-button>
      <el-button type="primary" :loading="dialog.saving" @click="save">建立</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.stock-hint {
  width: 100%;
  color: var(--el-text-color-secondary);
  font-size: 12px;
}
</style>
