<script setup>
import { onMounted, reactive, ref } from 'vue'
import api, { download, loadMeta, meta } from '../api'

const pad = (n) => String(n).padStart(2, '0')
const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

function periodOf(kind) {
  const now = new Date()
  const y = now.getFullYear()
  const m = now.getMonth()
  switch (kind) {
    case 'lastMonth':
      return [new Date(y, m - 1, 1), new Date(y, m, 0)]
    case 'quarter': {
      const q = Math.floor(m / 3) * 3
      return [new Date(y, q, 1), new Date(y, q + 3, 0)]
    }
    case 'lastQuarter': {
      const q = Math.floor(m / 3) * 3 - 3
      return [new Date(y, q, 1), new Date(y, q + 3, 0)]
    }
    case 'year':
      return [new Date(y, 0, 1), new Date(y, 11, 31)]
    case 'lastYear':
      return [new Date(y - 1, 0, 1), new Date(y - 1, 11, 31)]
    default:
      return [new Date(y, m, 1), new Date(y, m + 1, 0)]
  }
}

const quickPeriods = [
  { key: 'month', label: '本月' },
  { key: 'lastMonth', label: '上月' },
  { key: 'quarter', label: '本季' },
  { key: 'lastQuarter', label: '上季' },
  { key: 'year', label: '本年度' },
  { key: 'lastYear', label: '上年度' },
]

const filters = reactive({ range: periodOf('month').map(fmt), category: '' })
const result = ref(null)
const loading = ref(false)
const exporting = ref(false)

function params() {
  return { start: filters.range[0], end: filters.range[1], category: filters.category || undefined }
}

function setPeriod(key) {
  filters.range = periodOf(key).map(fmt)
  preview()
}

async function preview() {
  if (!filters.range) return
  loading.value = true
  try {
    result.value = (await api.get('/reports/summary', { params: params() })).data
  } finally {
    loading.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  try {
    await download('/reports/summary/export', params(), '申報彙整.xlsx')
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  await loadMeta()
  preview()
})
</script>

<template>
  <div class="page-toolbar">
    <el-date-picker v-model="filters.range" type="daterange" value-format="YYYY-MM-DD" :clearable="false" style="width: 260px" @change="preview" />
    <el-button-group>
      <el-button v-for="p in quickPeriods" :key="p.key" size="default" @click="setPeriod(p.key)">{{ p.label }}</el-button>
    </el-button-group>
    <el-select v-model="filters.category" placeholder="全部管制分類" clearable style="width: 160px" @change="preview">
      <el-option v-for="o in meta.control_categories" :key="o.value" v-bind="o" />
    </el-select>
    <span class="spacer" />
    <el-button type="success" icon="Download" :loading="exporting" @click="exportExcel">一鍵匯出 Excel</el-button>
  </div>

  <el-alert type="info" :closable="false" show-icon style="margin-bottom: 12px">
    期初存量 + 進貨量 − 使用量 − 移出量 = 期末存量。分裝、移轉屬廠內移動，不影響全廠總量。
  </el-alert>
  <el-alert
    v-for="w in result?.warnings"
    :key="w"
    type="error"
    :title="w"
    :closable="false"
    show-icon
    style="margin-bottom: 8px"
  />

  <el-table v-loading="loading" :data="result?.rows ?? []" border stripe empty-text="此期間無資料">
    <el-table-column type="index" label="序號" width="60" />
    <el-table-column prop="code" label="物品代號" width="110" />
    <el-table-column prop="name_zh" label="中文名稱" min-width="110" />
    <el-table-column prop="cas_no" label="CAS No." width="110" />
    <el-table-column prop="control_category_label" label="管制分類" width="110" />
    <el-table-column prop="unit" label="單位" width="60" />
    <el-table-column label="期初存量" align="right" width="110">
      <template #default="{ row }"><span class="num">{{ row.opening }}</span></template>
    </el-table-column>
    <el-table-column label="進貨量" align="right" width="100">
      <template #default="{ row }"><span class="num">{{ row.in_qty }}</span></template>
    </el-table-column>
    <el-table-column label="使用量" align="right" width="100">
      <template #default="{ row }"><span class="num">{{ row.use_qty }}</span></template>
    </el-table-column>
    <el-table-column label="移出量" align="right" width="100">
      <template #default="{ row }"><span class="num">{{ row.out_qty }}</span></template>
    </el-table-column>
    <el-table-column label="期末存量" align="right" width="110">
      <template #default="{ row }">
        <span class="num" :style="{ color: row.closing < 0 ? 'var(--el-color-danger)' : undefined, fontWeight: 600 }">{{ row.closing }}</span>
      </template>
    </el-table-column>
    <el-table-column label="廠內移動" align="right" min-width="90">
      <template #default="{ row }">{{ row.internal_moves }} 筆</template>
    </el-table-column>
  </el-table>
</template>
