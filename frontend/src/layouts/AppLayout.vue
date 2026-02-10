<script setup>
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

async function handleLogout() {
  await auth.logout()
}
</script>

<template>
  <div class="app-layout">
    <aside class="sidebar">
      <div class="sidebar-top">
        <div class="sidebar-logo">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M13 3L4 14h7l-2 7 9-11h-7l2-7z" fill="#4F6EF7" />
          </svg>
          <span>Daily Grow</span>
        </div>
        <div class="sidebar-account">{{ auth.user?.name || 'Название аккаунта' }}</div>

        <nav class="sidebar-nav">
          <div class="nav-section">
            <div class="nav-section-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
              </svg>
              Отзывы
            </div>
            <RouterLink to="/" class="nav-link" :class="{ active: route.name === 'reviews' }">
              Отзывы
            </RouterLink>
            <RouterLink to="/settings" class="nav-link" :class="{ active: route.name === 'settings' }">
              Настройка
            </RouterLink>
          </div>
        </nav>
      </div>
    </aside>

    <main class="main-content">
      <header class="main-header">
        <h1 class="page-title">{{ route.meta.title }}</h1>
        <button class="btn-logout" @click="handleLogout" title="Выйти">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
        </button>
      </header>
      <div class="main-body">
        <RouterView />
      </div>
    </main>
  </div>
</template>

<style scoped>
.app-layout {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 220px;
  min-width: 220px;
  background: #fff;
  border-right: 1px solid var(--color-border-light);
  display: flex;
  flex-direction: column;
  padding: 24px 0;
}

.sidebar-top {
  padding: 0 20px;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 17px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 6px;
}

.sidebar-account {
  font-size: 13px;
  color: var(--color-text-muted);
  margin-bottom: 28px;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
  padding: 8px 0;
  margin-bottom: 2px;
}

.nav-link {
  display: block;
  padding: 6px 0 6px 24px;
  font-size: 14px;
  color: var(--color-text-secondary);
  border-radius: 6px;
  transition: all 0.15s;
}

.nav-link:hover {
  color: var(--color-primary);
}

.nav-link.active {
  color: var(--color-primary);
  font-weight: 500;
}

.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.main-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 32px;
  background: #fff;
  border-bottom: 1px solid var(--color-border-light);
}

.page-title {
  font-size: 14px;
  font-weight: 400;
  color: var(--color-text-muted);
}

.btn-logout {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: none;
  background: none;
  color: var(--color-text-muted);
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.15s;
}

.btn-logout:hover {
  background: var(--color-border-light);
  color: var(--color-text);
}

.main-body {
  flex: 1;
  padding: 24px 32px;
}
</style>
