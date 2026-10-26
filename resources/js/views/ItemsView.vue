<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api, { download, labelOf, loadMeta, meta } from '../api'

const filters = reactive({ keyword: '', control_category: '', state: '' })
const page = reactive({ current: 1, size: 20, total: 0 })
const rows = ref([])
const loading = ref(false)

const categoryTag = { general: 'info', toxic: 'danger', priority: 'warning', dangerous: 'danger' }

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/items', { params: { ...filters, page: page.current, per_page: page.size } })
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

// ---- 新增 / 編輯 ----
const dialog = reactive({ visible: false, saving: false, editing: null })
const formRef = ref()
const form = reactive({})
const sdsFile = ref(null)
const rules = {
  code: [{ required: true, message: '請輸入物品代號' }],
  name_zh: [{ required: true, message: '請輸入中文名稱' }],
  control_category: [{ required: true, message: '請選擇管制分類' }],
}

function openForm(item = null) {
  dialog.editing = item
  sdsFile.value = null
  Object.assign(form, {
    code: item?.code ?? '',
    name_zh: item?.name_zh ?? '',
    name_en: item?.name_en ?? '',
    cas_no: item?.cas_no ?? '',
    state: item?.state ?? null,
    control_category: item?.control_category ?? 'general',
    hazard_labels: [...(item?.hazard_labels ?? [])],
    default_unit: item?.default_unit ?? null,
  })
  dialog.visible = true
}

function onSdsChange(file) {
  sdsFile.value = file.raw
}

async function save() {
  await formRef.value.validate()
  // 含檔案上傳，用 multipart；PUT 以 _method 模擬
  const fd = new FormData()
  for (const [key, value] of Object.entries(form)) {
    if (key === 'hazard_labels') fd.append(key, JSON.stringify(value))
    else fd.append(key, value ?? '') // 空字串在 Laravel 端會轉成 null
  }
  if (sdsFile.value) fd.append('sds', sdsFile.value)

  dialog.saving = true
  try {
    if (dialog.editing) {
      fd.append('_method', 'PUT')
      await api.post(`/items/${dialog.editing.id}`, fd)
    } else {
      await api.post('/items', fd)
    }
    ElMessage.success('已儲存')
    dialog.visible = false
    load()
  } finally {
    dialog.saving = false
  }
}

async function remove(item) {
  await ElMessageBox.confirm(`確定刪除「${item.code} ${item.name_zh}」？`, '刪除確認', { type: 'warning' })
  await api.delete(`/items/${item.id}`)
  ElMessage.success('已刪除')
  load()
}

function openSds(item) {
  window.open(`/api/items/${item.id}/sds`, '_blank')
}

async function removeSds() {
  await ElMessageBox.confirm('確定刪除已上傳的 SDS 檔案？', '刪除確認', { type: 'warning' })
  const { data } = await api.delete(`/items/${dialog.editing.id}/sds`)
  dialog.editing = data
  load()
}

// ---- 批次匯入 ----
const importDialog = reactive({ visible: false, uploading: false, file: null, result: null })

function openImport() {
  Object.assign(importDialog, { visible: true, file: null, result: null })
}

async function doImport() {
  if (!importDialog.file) return ElMessage.warning('請先選擇檔案')
  const fd = new FormData()
  fd.append('file', importDialog.file)
  importDialog.uploading = true
  try {
    const { data } = await api.post('/items/import', fd, { silent: true })
    importDialog.result = data
    ElMessage.success(`匯入完成：新增 ${data.created} 筆、更新 ${data.updated} 筆`)
    load()
  } catch (e) {
    importDialog.result = e.response?.data?.errors?.length !== undefined ? e.response.data : null
    if (!importDialog.result) ElMessage.error(e.response?.data?.message || '匯入失敗')
  } finally {
    importDialog.uploading = false
  }
}

onMounted(async () => {
  await loadMeta()
  load()
})
</script>

<template>
  <div class="page-toolbar">
    <el-input v-model="filters.keyword" placeholder="代號 / 名稱 / CAS No." clearable style="width: 240px" @keyup.enter="search" @clear="search" />
    <el-select v-model="filters.control_category" placeholder="管制分類" clearable style="width: 150px" @change="search">
      <el-option v-for="o in meta.control_categories" :key="o.value" v-bind="o" />
    </el-select>
    <el-select v-model="filters.state" placeholder="物品狀態" clearable style="width: 120px" @change="search">
      <el-option v-for="o in meta.item_states" :key="o.value" v-bind="o" />
    </el-select>
    <el-button icon="Search" @click="search">查詢</el-button>
    <span class="spacer" />
    <el-button icon="Upload" @click="openImport">Excel 批次匯入</el-button>
    <el-button type="primary" icon="Plus" @click="openForm()">新增物品</el-button>
  </div>

  <el-table v-loading="loading" :data="rows" border stripe>
    <el-table-column prop="code" label="物品代號" width="120" />
    <el-table-column prop="name_zh" label="中文名稱" min-width="120" />
    <el-table-column prop="name_en" label="英文名稱" min-width="140" />
    <el-table-column prop="cas_no" label="CAS No./管制編號" width="140" />
    <el-table-column label="狀態" width="70">
      <template #default="{ row }">{{ labelOf(meta.item_states, row.state) || '—' }}</template>
    </el-table-column>
    <el-table-column label="管制分類" width="120">
      <template #default="{ row }">
        <el-tag :type="categoryTag[row.control_category]" size="small">{{ labelOf(meta.control_categories, row.control_category) }}</el-tag>
      </template>
    </el-table-column>
    <el-table-column label="危害標示" min-width="160">
      <template #default="{ row }">
        <el-tag v-for="h in row.hazard_labels" :key="h" size="small" type="warning" effect="plain" style="margin: 2px">
          {{ labelOf(meta.hazard_labels, h) }}
        </el-tag>
      </template>
    </el-table-column>
    <el-table-column label="SDS" width="70" align="center">
      <template #default="{ row }">
        <el-button v-if="row.has_sds" link type="primary" icon="Document" @click="openSds(row)" />
        <span v-else>—</span>
      </template>
    </el-table-column>
    <el-table-column label="操作" width="130" fixed="right">
      <template #default="{ row }">
        <el-button link type="primary" @click="openForm(row)">編輯</el-button>
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

  <el-dialog v-model="dialog.visible" :title="dialog.editing ? '編輯物品' : '新增物品'" width="600px">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
      <el-form-item label="物品代號" prop="code"><el-input v-model="form.code" /></el-form-item>
      <el-form-item label="中文名稱" prop="name_zh"><el-input v-model="form.name_zh" /></el-form-item>
      <el-form-item label="英文名稱"><el-input v-model="form.name_en" /></el-form-item>
      <el-form-item label="CAS No./管制編號"><el-input v-model="form.cas_no" /></el-form-item>
      <el-form-item label="物品狀態">
        <el-radio-group v-model="form.state">
          <el-radio v-for="o in meta.item_states" :key="o.value" :value="o.value">{{ o.label }}</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="管制分類" prop="control_category">
        <el-select v-model="form.control_category">
          <el-option v-for="o in meta.control_categories" :key="o.value" v-bind="o" />
        </el-select>
      </el-form-item>
      <el-form-item label="危害標示">
        <el-checkbox-group v-model="form.hazard_labels">
          <el-checkbox v-for="o in meta.hazard_labels" :key="o.value" :value="o.value">{{ o.label }}</el-checkbox>
        </el-checkbox-group>
      </el-form-item>
      <el-form-item label="預設單位">
        <el-select v-model="form.default_unit" filterable allow-create clearable style="width: 140px">
          <el-option v-for="u in meta.units" :key="u" :label="u" :value="u" />
        </el-select>
      </el-form-item>
      <el-form-item label="SDS 檔案">
        <div>
          <div v-if="dialog.editing?.has_sds" style="margin-bottom: 6px">
            <el-button link type="primary" @click="openSds(dialog.editing)">{{ dialog.editing.sds_original_name || '檢視 SDS' }}</el-button>
            <el-button link type="danger" @click="removeSds">刪除</el-button>
          </div>
          <el-upload :auto-upload="false" :limit="1" accept="application/pdf" :on-change="onSdsChange" :on-remove="() => (sdsFile = null)">
            <el-button icon="Upload">{{ dialog.editing?.has_sds ? '重新上傳 PDF' : '選擇 PDF' }}</el-button>
          </el-upload>
        </div>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="dialog.visible = false">取消</el-button>
      <el-button type="primary" :loading="dialog.saving" @click="save">儲存</el-button>
    </template>
  </el-dialog>

  <el-dialog v-model="importDialog.visible" title="Excel 批次匯入" width="600px">
    <el-alert type="info" :closable="false" show-icon style="margin-bottom: 12px">
      以「物品代號」比對：已存在則更新，不存在則新增。任一列有錯誤時整批不匯入。
    </el-alert>
    <el-button icon="Download" @click="download('/items/import-template', {}, '物品主檔匯入範本.xlsx')">下載範本</el-button>
    <el-upload
      style="margin-top: 12px"
      drag
      :auto-upload="false"
      :limit="1"
      accept=".xlsx"
      :on-change="(f) => (importDialog.file = f.raw)"
      :on-remove="() => (importDialog.file = null)"
    >
      <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
      <div>將 .xlsx 拖曳至此或點擊選擇</div>
    </el-upload>

    <template v-if="importDialog.result?.errors?.length">
      <el-alert type="error" :closable="false" title="以下資料列有錯誤，未匯入任何資料" style="margin-top: 12px" />
      <el-table :data="importDialog.result.errors" size="small" max-height="240">
        <el-table-column prop="row" label="列號" width="70" />
        <el-table-column label="錯誤">
          <template #default="{ row }">{{ row.messages.join('；') }}</template>
        </el-table-column>
      </el-table>
    </template>

    <template #footer>
      <el-button @click="importDialog.visible = false">關閉</el-button>
      <el-button type="primary" :loading="importDialog.uploading" @click="doImport">開始匯入</el-button>
    </template>
  </el-dialog>
</template>
