<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api, { labelOf, loadMeta, meta } from '../api'

const filters = reactive({ item_id: null, location_id: null })
const rows = ref([])
const loading = ref(false)
const items = ref([])
const locationTree = ref([])

// 依物品 + 單位彙總全廠總量
const totals = computed(() => {
  const map = new Map()
  for (const s of rows.value) {
    const key = `${s.item_id}|${s.unit}`
    const t = map.get(key) ?? { item: s.item, unit: s.unit, quantity: 0, locations: 0 }
    t.quantity = Math.round((t.quantity + s.quantity) * 1000) / 1000
    t.locations++
    map.set(key, t)
  }
  return [...map.values()]
})

async function load() {
  loading.value = true
  try {
    rows.value = (await api.get('/stocks', { params: filters })).data
  } finally {
    loading.value = false
  }
}

async function rebuild() {
  await ElMessageBox.confirm('將依全部作業紀錄重新計算庫存，確定執行？', '重算庫存', { type: 'warning' })
  const { data } = await api.post('/stocks/rebuild')
  ElMessage.success(data.message)
  load()
}

onMounted(async () => {
  await loadMeta()
  const [i, t] = await Promise.all([api.get('/items/options'), api.get('/locations/tree')])
  items.value = i.data
  locationTree.value = t.data
  load()
})
</script>

<template>
  <div class="page-toolbar">
    <el-select v-model="filters.item_id" placeholder="物品" clearable filterable style="width: 220px" @change="load">
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
      style="width: 220px"
      @change="load"
    />
    <span class="spacer" />
    <el-button icon="Refresh" @click="rebuild">由作業紀錄重算庫存</el-button>
  </div>

  <el-row :gutter="16">
    <el-col :span="9">
      <el-card shadow="never" header="物品總量">
        <el-table :data="totals" size="small" empty-text="無庫存">
          <el-table-column label="物品" min-width="140">
            <template #default="{ row }">{{ row.item.code }} {{ row.item.name_zh }}</template>
          </el-table-column>
          <el-table-column label="分類" width="100">
            <template #default="{ row }">{{ labelOf(meta.control_categories, row.item.control_category) }}</template>
          </el-table-column>
          <el-table-column label="總量" align="right" width="110">
            <template #default="{ row }"><span class="num">{{ row.quantity }} {{ row.unit }}</span></template>
          </el-table-column>
        </el-table>
      </el-card>
    </el-col>
    <el-col :span="15">
      <el-card shadow="never" header="儲位明細">
        <el-table v-loading="loading" :data="rows" size="small" empty-text="無庫存">
          <el-table-column label="物品" min-width="140">
            <template #default="{ row }">{{ row.item.code }} {{ row.item.name_zh }}</template>
          </el-table-column>
          <el-table-column prop="location_path" label="儲位" min-width="240" />
          <el-table-column label="數量" align="right" width="110">
            <template #default="{ row }"><span class="num">{{ row.quantity }} {{ row.unit }}</span></template>
          </el-table-column>
          <el-table-column label="最後異動" width="150">
            <template #default="{ row }">{{ new Date(row.updated_at).toLocaleString('zh-TW', { hour12: false }) }}</template>
          </el-table-column>
        </el-table>
      </el-card>
    </el-col>
  </el-row>
</template>
