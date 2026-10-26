<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { menu } from './router'

const route = useRoute()
const router = useRouter()

// 依 group 分段顯示選單
const sections = computed(() => {
  const list = []
  for (const m of menu) {
    const last = list.at(-1)
    if (last && last.group === m.group) last.items.push(m)
    else list.push({ group: m.group, items: [m] })
  }
  return list
})

// 頂端搜尋：快速跳轉頁面
const keyword = ref('')
function searchPages(q, cb) {
  cb(menu.filter((m) => !q || m.title.includes(q) || m.group?.includes(q)).map((m) => ({ value: m.title, path: m.path })))
}
function go(item) {
  router.push(item.path)
  keyword.value = ''
}
</script>

<template>
  <div class="layout">
    <aside class="aside">
      <router-link to="/dashboard" class="brand">
        <span class="brand-logo"><el-icon><Box /></el-icon></span>
        <span class="brand-text">物品管理平台</span>
      </router-link>
      <nav class="menu">
        <template v-for="s in sections" :key="s.group ?? '_'">
          <div v-if="s.group" class="menu-header">{{ s.group }}</div>
          <router-link v-for="m in s.items" :key="m.path" :to="m.path" class="menu-item" :class="{ active: route.path === m.path }">
            <el-icon><component :is="m.icon" /></el-icon>
            <span>{{ m.title }}</span>
          </router-link>
        </template>
      </nav>
    </aside>

    <div class="main">
      <header class="navbar">
        <el-icon class="navbar-search-icon"><Search /></el-icon>
        <el-autocomplete
          v-model="keyword"
          :fetch-suggestions="searchPages"
          placeholder="搜尋功能..."
          class="navbar-search"
          @select="go"
        />
        <span class="spacer" />
        <span class="navbar-title">{{ route.meta.group ? `${route.meta.group} / ` : '' }}<b>{{ route.meta.title }}</b></span>
        <el-avatar :size="36" class="avatar"><el-icon><UserFilled /></el-icon></el-avatar>
      </header>

      <main class="content">
        <router-view v-if="route.meta.bare" />
        <div v-else class="page-card">
          <h2 class="page-title">{{ route.meta.title }}</h2>
          <router-view />
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
.layout {
  display: flex;
  min-height: 100%;
  background: var(--app-bg);
}

/* ---- 側邊欄 ---- */
.aside {
  position: sticky;
  top: 0;
  width: 260px;
  height: 100vh;
  flex-shrink: 0;
  overflow-y: auto;
  background: #fff;
  box-shadow: 0 0.125rem 0.375rem rgba(161, 172, 184, 0.12);
}
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 64px;
  padding: 0 26px;
  text-decoration: none;
}
.brand-logo {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  color: #fff;
  font-size: 18px;
  background: var(--el-color-primary);
}
.brand-text {
  color: var(--app-heading);
  font-size: 20px;
  font-weight: 700;
  letter-spacing: 0.5px;
}
.menu {
  padding: 8px 0 24px;
}
.menu-header {
  margin: 20px 0 6px;
  padding: 0 30px;
  color: #a1acb8;
  font-size: 12px;
  letter-spacing: 0.5px;
}
.menu-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 2px 16px;
  padding: 9px 14px;
  border-radius: 6px;
  color: var(--app-text);
  font-size: 15px;
  text-decoration: none;
  transition: background 0.2s, color 0.2s;
}
.menu-item .el-icon {
  font-size: 18px;
}
.menu-item:hover {
  background: rgba(67, 89, 113, 0.04);
}
.menu-item.active {
  color: var(--el-color-primary);
  font-weight: 600;
  background: var(--el-color-primary-light-9);
}
.menu-item.active::after {
  content: '';
  position: absolute;
  right: -16px;
  top: 0;
  bottom: 0;
  width: 4px;
  border-radius: 4px 0 0 4px;
  background: var(--el-color-primary);
}

/* ---- 頂端列 ---- */
.main {
  flex: 1;
  min-width: 0;
  padding: 0 24px;
}
.navbar {
  position: sticky;
  top: 12px;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 12px;
  height: 60px;
  margin-top: 12px;
  padding: 0 20px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.95);
  box-shadow: var(--app-shadow);
  backdrop-filter: blur(6px);
}
.navbar-search-icon {
  color: var(--app-text);
  font-size: 18px;
}
.navbar-search {
  width: 260px;
}
.navbar-search :deep(.el-input__wrapper) {
  box-shadow: none !important;
  padding-left: 0;
}
.navbar-title {
  color: #a1acb8;
  font-size: 14px;
}
.navbar-title b {
  color: var(--app-heading);
  font-weight: 600;
}
.avatar {
  background: var(--el-color-primary-light-7);
  color: var(--el-color-primary);
}
.spacer {
  flex: 1;
}

/* ---- 內容 ---- */
.content {
  padding: 24px 0;
}
.page-card {
  padding: 20px 24px 24px;
  border-radius: 8px;
  background: #fff;
  box-shadow: var(--app-shadow);
}
.page-title {
  margin: 0 0 16px;
  color: var(--app-heading);
  font-size: 18px;
  font-weight: 600;
}
</style>
