<script setup>
import { computed, onMounted, ref } from 'vue'
import api, { labelOf, loadMeta, meta } from '../api'

const pad = (n) => String(n).padStart(2, '0')
const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
const monthKey = (s) => s.slice(0, 7)

const typeColor = { in: '#696cff', use: '#03c3ec', split: '#8592a3', transfer: '#ffab00', out: '#ff3e1d' }
const typeIcon = { in: 'Download', use: 'Odometer', split: 'Operation', transfer: 'Switch', out: 'Upload' }

const loading = ref(true)
const items = ref([])
const locations = ref([])
const stocks = ref([])
const txs = ref([])

// 近 6 個月
const months = (() => {
  const now = new Date()
  return Array.from({ length: 6 }, (_, i) => {
    const d = new Date(now.getFullYear(), now.getMonth() - 5 + i, 1)
    return { key: `${d.getFullYear()}-${pad(d.getMonth() + 1)}`, label: `${d.getMonth() + 1}月` }
  })
})()
const thisMonth = months.at(-1).key
const lastMonth = months.at(-2).key

const monthTxs = computed(() => txs.value.filter((t) => monthKey(t.occurred_at) === thisMonth))
const lastMonthCount = computed(() => txs.value.filter((t) => monthKey(t.occurred_at) === lastMonth).length)
const growth = computed(() => {
  if (!lastMonthCount.value) return null
  return Math.round(((monthTxs.value.length - lastMonthCount.value) / lastMonthCount.value) * 100)
})

const stats = computed(() => [
  { title: '物品主檔', value: items.value.length, unit: '項', icon: 'Box', color: '#71dd37', to: '/items' },
  { title: '儲位', value: locations.value.filter((l) => l.level === 'location').length, unit: '個', icon: 'OfficeBuilding', color: '#03c3ec', to: '/locations' },
  { title: '有庫存物品', value: new Set(stocks.value.map((s) => s.item_id)).size, unit: '項', icon: 'DataBoard', color: '#ff3e1d', to: '/stocks' },
  { title: '本月作業', value: monthTxs.value.length, unit: '筆', icon: 'Tickets', color: '#696cff', to: '/transactions', growth: growth.value },
])

// 長條圖：每月入庫 / 使用筆數
const chartSeries = [
  { type: 'in', label: '入庫', color: typeColor.in },
  { type: 'use', label: '使用', color: typeColor.use },
]
const bars = computed(() =>
  months.map((m) => ({
    ...m,
    values: chartSeries.map((s) => txs.value.filter((t) => t.type === s.type && monthKey(t.occurred_at) === m.key).length),
  })),
)
const chartMax = computed(() => Math.max(4, ...bars.value.flatMap((b) => b.values)))
const chartTicks = computed(() => {
  const step = Math.ceil(chartMax.value / 4)
  return [4, 3, 2, 1, 0].map((i) => i * step)
})

// 環狀圖：本月作業類型分布
const donut = computed(() => {
  const total = monthTxs.value.length
  const circ = 2 * Math.PI * 52
  let offset = 0
  return (meta.transaction_types ?? []).map((t) => {
    const count = monthTxs.value.filter((x) => x.type === t.value).length
    const len = total ? (count / total) * circ : 0
    const seg = { ...t, count, color: typeColor[t.value], dash: `${len} ${circ - len}`, offset: -offset }
    offset += len
    return seg
  })
})
const inRatio = computed(() => (monthTxs.value.length ? Math.round((donut.value.find((d) => d.value === 'in')?.count / monthTxs.value.length) * 100) : 0))

// 依管制分類統計物品數
const categories = computed(() => {
  const total = items.value.length || 1
  return (meta.control_categories ?? [])
    .map((c) => {
      const count = items.value.filter((i) => i.control_category === c.value).length
      return { ...c, count, percent: Math.round((count / total) * 100) }
    })
    .filter((c) => c.count)
})

const recent = computed(() => txs.value.slice(0, 6))

onMounted(async () => {
  const start = fmt(new Date(new Date().getFullYear(), new Date().getMonth() - 5, 1))
  try {
    const [, i, l, s, t] = await Promise.all([
      loadMeta(),
      api.get('/items/options'),
      api.get('/locations'),
      api.get('/stocks'),
      api.get('/transactions', { params: { start, per_page: 1000 } }),
    ])
    items.value = i.data
    locations.value = l.data
    stocks.value = s.data
    txs.value = t.data.data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-loading="loading" class="dashboard">
    <!-- 第一列：歡迎卡 + 4 個統計卡 -->
    <div class="card welcome">
      <div class="welcome-body">
        <h3>歡迎回來！🎉</h3>
        <p>
          本月已完成 <b>{{ monthTxs.length }}</b> 筆廠內作業<template v-if="growth !== null">，較上月 <b>{{ growth >= 0 ? '增加' : '減少' }} {{ Math.abs(growth) }}%</b></template>。
          別忘了定期匯出政府申報資料。
        </p>
        <el-button plain type="primary" size="small" @click="$router.push('/transactions')">新增作業紀錄</el-button>
      </div>
      <svg class="welcome-art" viewBox="0 0 200 140" aria-hidden="true">
        <rect x="20" y="96" width="160" height="8" rx="4" fill="#e1e2ff" />
        <rect x="40" y="40" width="44" height="56" rx="6" fill="#696cff" />
        <rect x="92" y="20" width="44" height="76" rx="6" fill="#9698ff" />
        <rect x="144" y="58" width="30" height="38" rx="6" fill="#03c3ec" />
        <path d="M48 56h28M48 66h20M100 36h28M100 46h20" stroke="#fff" stroke-width="4" stroke-linecap="round" />
        <circle cx="166" cy="30" r="12" fill="#ffab00" opacity=".85" />
      </svg>
    </div>

    <router-link v-for="s in stats" :key="s.title" :to="s.to" class="card stat">
      <span class="stat-icon" :style="{ color: s.color, background: `${s.color}22` }"><el-icon><component :is="s.icon" /></el-icon></span>
      <span class="stat-title">{{ s.title }}</span>
      <span class="stat-value num">{{ s.value.toLocaleString() }} <small>{{ s.unit }}</small></span>
      <span v-if="s.growth != null" class="stat-trend" :class="s.growth >= 0 ? 'up' : 'down'">
        <el-icon><component :is="s.growth >= 0 ? 'Top' : 'Bottom'" /></el-icon>{{ Math.abs(s.growth) }}%
      </span>
      <span v-else class="stat-trend muted">查看詳情</span>
    </router-link>

    <!-- 第二列：長條圖 + 環狀圖 -->
    <div class="card chart-card">
      <div class="chart-main">
        <div class="card-head">
          <h4>近六個月作業量</h4>
          <span class="legend">
            <span v-for="s in chartSeries" :key="s.type"><i :style="{ background: s.color }" />{{ s.label }}</span>
          </span>
        </div>
        <div class="bar-chart">
          <div class="y-axis">
            <span v-for="t in chartTicks" :key="t">{{ t }}</span>
          </div>
          <div class="plot">
            <div v-for="b in bars" :key="b.key" class="bar-group">
              <div class="bar-stack">
                <el-tooltip v-for="(v, idx) in b.values" :key="idx" :content="`${chartSeries[idx].label} ${v} 筆`" placement="top">
                  <span class="bar" :style="{ height: `${(v / chartTicks[0]) * 100}%`, background: chartSeries[idx].color }" />
                </el-tooltip>
              </div>
              <span class="x-label">{{ b.label }}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="chart-side">
        <el-tag type="primary" effect="light">{{ months.at(-1).label }}</el-tag>
        <svg viewBox="0 0 140 140" class="donut">
          <circle cx="70" cy="70" r="52" fill="none" stroke="#f0f0ff" stroke-width="14" />
          <circle
            v-for="d in donut"
            :key="d.value"
            cx="70"
            cy="70"
            r="52"
            fill="none"
            :stroke="d.color"
            stroke-width="14"
            :stroke-dasharray="d.dash"
            :stroke-dashoffset="d.offset"
            transform="rotate(-90 70 70)"
          />
          <text x="70" y="68" text-anchor="middle" class="donut-value">{{ inRatio }}%</text>
          <text x="70" y="88" text-anchor="middle" class="donut-label">入庫占比</text>
        </svg>
        <div class="donut-legend">
          <span v-for="d in donut" :key="d.value"><i :style="{ background: d.color }" />{{ d.label }} <b class="num">{{ d.count }}</b></span>
        </div>
      </div>
    </div>

    <!-- 第三列：管制分類 + 最近作業 -->
    <div class="card list-card wide">
      <div class="card-head">
        <h4>物品管制分類</h4>
        <span class="muted">共 {{ items.length }} 項</span>
      </div>
      <el-empty v-if="!categories.length" :image-size="60" description="尚無物品" />
      <div v-for="c in categories" :key="c.value" class="cat-row">
        <div class="cat-text">
          <span>{{ c.label }}</span>
          <b class="num">{{ c.count }}</b>
        </div>
        <el-progress :percentage="c.percent" :show-text="false" :stroke-width="8" />
      </div>
    </div>

    <div class="card list-card recent">
      <div class="card-head">
        <h4>最近作業</h4>
        <router-link to="/transactions" class="link">查看全部</router-link>
      </div>
      <el-empty v-if="!recent.length" :image-size="60" description="尚無作業紀錄" />
      <div v-for="t in recent" :key="t.id" class="tx-row">
        <span class="tx-icon" :style="{ color: typeColor[t.type], background: `${typeColor[t.type]}22` }">
          <el-icon><component :is="typeIcon[t.type]" /></el-icon>
        </span>
        <div class="tx-text">
          <small>{{ t.type_label }} · {{ t.occurred_at.slice(0, 16) }}</small>
          <span>{{ t.item.code }} {{ t.item.name_zh }}</span>
        </div>
        <div class="tx-qty num">
          {{ t.type === 'in' ? '+' : t.type === 'use' || t.type === 'out' ? '−' : '' }}{{ t.quantity }}
          <small>{{ t.unit }}</small>
        </div>
      </div>
    </div>

    <div class="card list-card">
      <div class="card-head">
        <h4>庫存儲位分布</h4>
        <router-link to="/stocks" class="link">即時庫存</router-link>
      </div>
      <el-empty v-if="!stocks.length" :image-size="60" description="尚無庫存" />
      <div v-for="s in stocks.slice(0, 6)" :key="s.id" class="tx-row">
        <span class="tx-icon" style="color: #696cff; background: #696cff22"><el-icon><Location /></el-icon></span>
        <div class="tx-text">
          <small>{{ labelOf(meta.control_categories, s.item.control_category) }}</small>
          <span>{{ s.item.name_zh }}</span>
          <small class="ellipsis">{{ s.location_path }}</small>
        </div>
        <div class="tx-qty num">{{ s.quantity }} <small>{{ s.unit }}</small></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 24px;
  color: var(--app-text);
}
.card {
  padding: 20px 24px;
  border-radius: 8px;
  background: #fff;
  box-shadow: var(--app-shadow);
}
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
h3,
h4 {
  margin: 0;
  color: var(--app-heading);
  font-weight: 600;
}
h4 {
  font-size: 16px;
}
.muted {
  color: #a1acb8;
  font-size: 13px;
}
.link {
  color: var(--el-color-primary);
  font-size: 13px;
  text-decoration: none;
}

/* 歡迎卡 */
.welcome {
  grid-column: span 2;
  grid-row: span 2;
  display: flex;
  align-items: center;
  gap: 16px;
}
.welcome-body {
  flex: 1;
}
.welcome h3 {
  color: var(--el-color-primary);
  font-size: 18px;
  margin-bottom: 12px;
}
.welcome p {
  margin: 0 0 20px;
  line-height: 1.7;
  font-size: 14px;
}
.welcome-art {
  width: 40%;
  max-width: 220px;
}

/* 統計卡 */
.stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
  text-decoration: none;
  color: inherit;
  transition: transform 0.2s;
}
.stat:hover {
  transform: translateY(-2px);
}
.stat-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  margin-bottom: 12px;
  border-radius: 6px;
  font-size: 20px;
}
.stat-title {
  font-size: 14px;
}
.stat-value {
  color: var(--app-heading);
  font-size: 24px;
  font-weight: 600;
}
.stat-value small {
  font-size: 14px;
  font-weight: 400;
  color: #a1acb8;
}
.stat-trend {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 13px;
}
.stat-trend.up {
  color: #71dd37;
}
.stat-trend.down {
  color: #ff3e1d;
}

/* 圖表卡 */
.chart-card {
  grid-column: span 4;
  display: flex;
  padding: 0;
}
.chart-main {
  flex: 1;
  min-width: 0;
  padding: 20px 24px;
}
.chart-side {
  width: 280px;
  padding: 20px 24px;
  border-left: 1px solid #eceef1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}
.legend {
  display: flex;
  gap: 16px;
  font-size: 13px;
}
.legend i,
.donut-legend i {
  display: inline-block;
  width: 8px;
  height: 8px;
  margin-right: 6px;
  border-radius: 50%;
}
.bar-chart {
  display: flex;
  height: 240px;
}
.y-axis {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 0 12px 24px 0;
  font-size: 12px;
  color: #a1acb8;
  text-align: right;
}
.y-axis span {
  line-height: 0;
}
.plot {
  flex: 1;
  display: flex;
  background: repeating-linear-gradient(to bottom, #eceef1 0 1px, transparent 1px 25%) top / 100% calc(100% - 24px) no-repeat;
}
.bar-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.bar-stack {
  flex: 1;
  display: flex;
  align-items: flex-end;
  gap: 6px;
  width: 100%;
  justify-content: center;
}
.bar {
  width: 12px;
  min-height: 2px;
  border-radius: 6px 6px 2px 2px;
  transition: height 0.4s;
}
.x-label {
  height: 24px;
  line-height: 24px;
  font-size: 12px;
  color: #a1acb8;
}
.donut {
  width: 170px;
}
.donut circle {
  transition: stroke-dasharray 0.4s;
}
.donut-value {
  fill: var(--app-heading);
  font-size: 22px;
  font-weight: 600;
}
.donut-label {
  fill: #a1acb8;
  font-size: 11px;
}
.donut-legend {
  display: grid;
  grid-template-columns: repeat(2, auto);
  gap: 6px 16px;
  font-size: 13px;
}
.donut-legend b {
  margin-left: 4px;
  color: var(--app-heading);
}

/* 清單卡 */
.list-card {
  grid-column: span 1;
}
.list-card.wide {
  grid-column: span 2;
}
.cat-row {
  margin-bottom: 14px;
}
.cat-text {
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;
  font-size: 14px;
}
.cat-text b {
  color: var(--app-heading);
}
.tx-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
}
.tx-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 38px;
  height: 38px;
  border-radius: 6px;
  font-size: 18px;
}
.tx-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  font-size: 14px;
  color: var(--app-heading);
}
.tx-text small {
  color: #a1acb8;
  font-size: 12px;
}
.ellipsis {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.tx-qty {
  color: var(--app-heading);
  font-weight: 600;
  white-space: nowrap;
}
.tx-qty small {
  color: #a1acb8;
  font-weight: 400;
}

@media (max-width: 1200px) {
  .dashboard {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .chart-card,
  .list-card,
  .list-card.wide {
    grid-column: span 2;
  }
}
</style>
