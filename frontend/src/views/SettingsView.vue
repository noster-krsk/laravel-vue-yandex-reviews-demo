<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'

const yandexUrl = ref('')
const saved = ref(false)
const loading = ref(false)
const loadingPage = ref(true)
const errors = ref({})
const parsingRestarted = ref(false)

async function fetchSettings() {
  loadingPage.value = true
  try {
    const { data } = await api.get('/settings')
    yandexUrl.value = data.yandex_url || ''
  } catch {
    yandexUrl.value = ''
  } finally {
    loadingPage.value = false
  }
}

async function save() {
  errors.value = {}
  saved.value = false
  parsingRestarted.value = false
  loading.value = true
  try {
    const { data } = await api.post('/settings', { yandex_url: yandexUrl.value })
    saved.value = true
    parsingRestarted.value = data.new_parsing_started || false

    setTimeout(() => {
      saved.value = false
      parsingRestarted.value = false
    }, 5000)
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {}
    } else {
      errors.value = { yandex_url: ['Ошибка сохранения'] }
    }
  } finally {
    loading.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div class="settings-page">
    <div v-if="loadingPage" class="loading">Загрузка...</div>

    <div v-else class="settings-card">
      <h2 class="settings-title">Подключить Яндекс</h2>
      <p class="settings-hint">
        Укажите ссылку на Яндекс, пример
        <span class="hint-link">https://yandex.ru/maps/org/samoye_populyarnoye_kafe/1010501395/reviews/</span>
      </p>

      <form @submit.prevent="save">
        <div class="form-group">
          <input
            v-model="yandexUrl"
            type="url"
            placeholder="https://yandex.ru/maps/org/samoye_populyarnoye_kafe/1010501395/reviews/"
          />
          <span v-if="errors.yandex_url" class="error">{{ errors.yandex_url[0] }}</span>
        </div>

        <button type="submit" class="btn-save" :disabled="loading">
          {{ loading ? 'Сохранение...' : 'Сохранить' }}
        </button>

        <span v-if="saved && !parsingRestarted" class="saved-msg">✅ Сохранено</span>

        <div v-if="parsingRestarted" class="restart-msg">
          🔄 Старый парсинг остановлен, новый запущен. Перейдите на страницу отзывов для просмотра прогресса.
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.settings-page {
  max-width: 640px;
}

.settings-card {
  background: #fff;
  border: 1px solid var(--color-border-light);
  border-radius: 10px;
  padding: 32px;
}

.settings-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 8px;
}

.settings-hint {
  font-size: 13px;
  color: var(--color-text-muted);
  margin-bottom: 20px;
  line-height: 1.5;
}

.hint-link {
  color: var(--color-primary);
  word-break: break-all;
}

.form-group {
  margin-bottom: 16px;
}

.form-group input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
  color: var(--color-text);
  background: #fff;
}

.form-group input:focus {
  border-color: var(--color-primary);
}

.form-group input::placeholder {
  color: #c5c9d2;
}

.error {
  display: block;
  color: var(--color-error);
  font-size: 12px;
  margin-top: 4px;
}

.btn-save {
  padding: 10px 28px;
  background: var(--color-primary);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-save:hover {
  background: var(--color-primary-hover);
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.saved-msg {
  margin-left: 12px;
  font-size: 13px;
  color: #22c55e;
}

.restart-msg {
  margin-top: 12px;
  padding: 10px 14px;
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 8px;
  font-size: 13px;
  color: #92400e;
  line-height: 1.5;
}

.loading {
  text-align: center;
  padding: 48px 0;
  color: var(--color-text-muted);
}
</style>
