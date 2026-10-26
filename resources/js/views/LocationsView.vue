<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api, { labelOf, loadMeta, meta } from '../api'

const tree = ref([])
const loading = ref(false)
const current = ref(null)
const stocks = ref([])

const dialog = reactive({ visible: false, saving: false, editing: null, parent: null })
const formRef = ref()
const form = reactive({})
const rules = {
  code: [{ required: true, message: '請輸入儲位編號' }],
  name: [{ required: true, message: '請輸入名稱' }],
}

const childLevel = computed(() => {
  const level = meta.location_levels?.find((l) => l.value === current.value?.level)
  return level?.child ?? null
})

// 由最上層到目前節點的路徑
const path = computed(() => {
  const list = []
  for (let n = current.value; n; n = n.parent_id ? findNode(tree.value, n.parent_id) : null) list.unshift(n)
  return list
})

// 導覽列各段：同層可切換，最後再接一段下層選單
const segments = computed(() => {
  const levelLabel = (v) => labelOf(meta.location_levels, v)
  const segs = path.value.map((node, i) => ({
    level: node.level,
    label: levelLabel(node.level),
    node,
    options: i ? path.value[i - 1].children : tree.value,
  }))
  if (!segs.length && tree.value.length) segs.push({ level: 'factory', label: levelLabel('factory'), node: null, options: tree.value })
  if (current.value?.children?.length) {
    segs.push({ level: childLevel.value, label: levelLabel(childLevel.value), node: null, options: current.value.children })
  }
  return segs
})

async function load() {
  loading.value = true
  try {
    tree.value = (await api.get('/locations/tree')).data
    current.value = (current.value && findNode(tree.value, current.value.id)) || tree.value[0] || null
    current.value && loadStocks()
  } finally {
    loading.value = false
  }
}

function findNode(nodes, id) {
  for (const n of nodes) {
    if (n.id === id) return n
    const found = findNode(n.children, id)
    if (found) return found
  }
  return null
}

async function select(node) {
  current.value = node
  loadStocks()
}

async function loadStocks() {
  stocks.value = (await api.get('/stocks', { params: { location_id: current.value.id } })).data
}

function openCreate(parent) {
  dialog.editing = null
  dialog.parent = parent
  Object.assign(form, {
    code: parent ? `${parent.code}-` : '',
    name: '',
    level: parent ? childLevel.value : 'factory',
    parent_id: parent?.id ?? null,
    type: null,
    capacity: null,
    capacity_unit: null,
    remark: '',
  })
  dialog.visible = true
}

function openEdit(node) {
  dialog.editing = node
  dialog.parent = node.parent_id ? findNode(tree.value, node.parent_id) : null
  Object.assign(form, {
    code: node.code,
    name: node.name,
    level: node.level,
    parent_id: node.parent_id,
    type: node.type,
    capacity: node.capacity,
    capacity_unit: node.capacity_unit,
    remark: node.remark,
  })
  dialog.visible = true
}

async function save() {
  await formRef.value.validate()
  dialog.saving = true
  try {
    if (dialog.editing) {
      await api.put(`/locations/${dialog.editing.id}`, form)
    } else {
      const res = await api.post('/locations', form)
      current.value = res.data
    }
    ElMessage.success('已儲存')
    dialog.visible = false
    await load()
  } finally {
    dialog.saving = false
  }
}

async function remove(node) {
  await ElMessageBox.confirm(`確定刪除「${node.name}」？`, '刪除確認', { type: 'warning' })
  await api.delete(`/locations/${node.id}`)
  ElMessage.success('已刪除')
  if (current.value?.id === node.id) current.value = node.parent_id ? findNode(tree.value, node.parent_id) : null
  await load()
}

onMounted(async () => {
  await loadMeta()
  await load()
})
</script>

<template>
  <!-- 廠區階層導覽列：每一段可切換同層節點，最後一段可往下層選 -->
  <div v-loading="loading" class="loc-nav">
    <el-icon class="loc-nav-icon"><OfficeBuilding /></el-icon>
    <template v-for="(seg, idx) in segments" :key="seg.level">
      <el-icon v-if="idx" class="sep"><ArrowRight /></el-icon>
      <el-dropdown trigger="click" max-height="320px" @command="select">
        <button type="button" class="seg" :class="{ active: seg.node && seg.node.id === current?.id, placeholder: !seg.node }">
          <small>{{ seg.label }}</small>
          <span>{{ seg.node ? seg.node.name : `選擇${seg.label}` }}</span>
          <el-icon class="caret"><ArrowDown /></el-icon>
        </button>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item v-for="o in seg.options" :key="o.id" :command="o" :class="{ 'is-current': o.id === seg.node?.id }">
              {{ o.name }}<span class="code">{{ o.code }}</span>
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </template>
    <span v-if="!tree.length && !loading" class="code">尚無資料，請先新增廠區</span>
    <span class="spacer" />
    <el-button type="primary" icon="Plus" @click="openCreate(null)">新增廠區</el-button>
  </div>

  <div v-if="current" class="loc-info">
    <div class="page-toolbar">
      <el-tag effect="light">{{ current.level_label }}</el-tag>
      <span class="loc-title">{{ current.name }}</span>
      <span class="code">{{ current.code }}</span>
      <span class="spacer" />
      <el-button v-if="childLevel" icon="Plus" size="small" @click="openCreate(current)">
        新增{{ labelOf(meta.location_levels, childLevel) }}
      </el-button>
      <el-button icon="Edit" size="small" @click="openEdit(current)">編輯</el-button>
      <el-button icon="Delete" size="small" type="danger" plain @click="remove(current)">刪除</el-button>
    </div>
    <el-descriptions :column="2" border>
      <el-descriptions-item label="儲位編號">{{ current.code }}</el-descriptions-item>
      <el-descriptions-item label="階層">{{ current.level_label }}</el-descriptions-item>
      <el-descriptions-item label="儲位類型">{{ current.type || '—' }}</el-descriptions-item>
      <el-descriptions-item label="容量上限">
        {{ current.capacity != null ? `${current.capacity} ${current.capacity_unit || ''}` : '—' }}
      </el-descriptions-item>
      <el-descriptions-item label="備註" :span="2">{{ current.remark || '—' }}</el-descriptions-item>
    </el-descriptions>

    <template v-if="current.children?.length">
      <h4>下層{{ labelOf(meta.location_levels, childLevel) }}（{{ current.children.length }}）</h4>
      <div class="children">
        <button v-for="c in current.children" :key="c.id" type="button" class="child" @click="select(c)">
          <span>{{ c.name }}</span>
          <span class="code">{{ c.code }}</span>
        </button>
      </div>
    </template>

    <h4>目前庫存（含下層儲位）</h4>
    <el-table :data="stocks" size="small" empty-text="無庫存">
      <el-table-column label="物品" min-width="160">
        <template #default="{ row }">{{ row.item.code }} {{ row.item.name_zh }}</template>
      </el-table-column>
      <el-table-column prop="location_path" label="儲位" min-width="220" />
      <el-table-column label="數量" align="right" width="130">
        <template #default="{ row }"><span class="num">{{ row.quantity }} {{ row.unit }}</span></template>
      </el-table-column>
    </el-table>
  </div>
  <el-empty v-else-if="!loading" description="請從上方導覽列選擇節點" />

  <el-dialog v-model="dialog.visible" :title="dialog.editing ? '編輯節點' : '新增節點'" width="520px">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
      <el-form-item label="上級">{{ dialog.parent ? dialog.parent.name : '（無，最上層）' }}</el-form-item>
      <el-form-item label="階層">{{ labelOf(meta.location_levels, form.level) }}</el-form-item>
      <el-form-item label="儲位編號" prop="code"><el-input v-model="form.code" /></el-form-item>
      <el-form-item label="名稱" prop="name"><el-input v-model="form.name" /></el-form-item>
      <template v-if="form.level === 'location'">
        <el-form-item label="儲位類型">
          <el-select v-model="form.type" filterable allow-create clearable placeholder="選擇或輸入">
            <el-option v-for="t in meta.location_types" :key="t" :label="t" :value="t" />
          </el-select>
        </el-form-item>
        <el-form-item label="容量上限">
          <el-input-number v-model="form.capacity" :min="0" :precision="3" controls-position="right" />
          <el-select v-model="form.capacity_unit" filterable allow-create clearable placeholder="單位" style="width: 110px; margin-left: 8px">
            <el-option v-for="u in meta.units" :key="u" :label="u" :value="u" />
          </el-select>
        </el-form-item>
      </template>
      <el-form-item label="備註"><el-input v-model="form.remark" type="textarea" /></el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="dialog.visible = false">取消</el-button>
      <el-button type="primary" :loading="dialog.saving" @click="save">儲存</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.code {
  margin-left: 6px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
}

/* ---- 階層導覽列 ---- */
.loc-nav {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  min-height: 56px;
  margin-bottom: 20px;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--el-color-primary-light-9);
}
.loc-nav-icon {
  margin-right: 6px;
  color: var(--el-color-primary);
  font-size: 20px;
}
.sep {
  color: #a1acb8;
}
.seg {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-start;
  position: relative;
  padding: 4px 26px 4px 10px;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: var(--app-heading);
  font: inherit;
  font-size: 15px;
  cursor: pointer;
  transition: background 0.2s;
}
.seg small {
  color: #a1acb8;
  font-size: 11px;
}
.seg:hover {
  background: #fff;
}
.seg.active {
  background: #fff;
  color: var(--el-color-primary);
  font-weight: 600;
  box-shadow: var(--app-shadow);
}
.seg.placeholder span {
  color: #a1acb8;
}
.caret {
  position: absolute;
  right: 8px;
  bottom: 8px;
  font-size: 12px;
}
.is-current {
  color: var(--el-color-primary);
  font-weight: 600;
}
.spacer {
  flex: 1;
}

/* ---- 節點資訊 ---- */
.loc-title {
  color: var(--app-heading);
  font-size: 18px;
  font-weight: 600;
}
.loc-info h4 {
  margin: 24px 0 12px;
  color: var(--app-heading);
}
.children {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.child {
  padding: 8px 14px;
  border: 1px solid #eceef1;
  border-radius: 6px;
  background: #fff;
  color: var(--app-heading);
  font: inherit;
  font-size: 14px;
  cursor: pointer;
  transition: border-color 0.2s, color 0.2s;
}
.child:hover {
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
}
</style>
