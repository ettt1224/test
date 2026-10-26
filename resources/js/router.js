import { createRouter, createWebHistory } from 'vue-router'

export const menu = [
  { path: '/dashboard', title: '總覽', icon: 'HomeFilled', component: () => import('./views/DashboardView.vue'), bare: true },
  { group: '基本資料', path: '/locations', title: '廠區儲位管理', icon: 'OfficeBuilding', component: () => import('./views/LocationsView.vue') },
  { group: '基本資料', path: '/items', title: '物品主檔管理', icon: 'Box', component: () => import('./views/ItemsView.vue') },
  { group: '作業與庫存', path: '/transactions', title: '廠內作業紀錄', icon: 'Tickets', component: () => import('./views/TransactionsView.vue') },
  { group: '作業與庫存', path: '/stocks', title: '即時庫存', icon: 'DataBoard', component: () => import('./views/StocksView.vue'), bare: true },
  { group: '報表', path: '/reports', title: '政府申報匯出', icon: 'Document', component: () => import('./views/ReportsView.vue') },
]

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/dashboard' },
    ...menu.map((m) => ({ path: m.path, component: m.component, meta: { title: m.title, group: m.group, bare: m.bare } })),
  ],
})
