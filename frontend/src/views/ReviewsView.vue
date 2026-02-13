<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/api/axios'

const reviews = ref([])
const statistics = ref({ average_rating: 0, total: 0, positive: 0, negative: 0, neutral: 0 })
const organization = ref({})
const cachedAt = ref(null)

const loading = ref(true)
const loadingMore = ref(false)
const isParsing = ref(false)
const isComplete = ref(false)

const currentPage = ref(1)
const hasMore = ref(false)
const totalReviews = ref(0)

let pollingTimer = null

async function fetchReviews(page = 1) {
  if (page === 1) {
    loading.value = true
  } else {
    loadingMore.value = true
  }

  try {
    const { data } = await api.get('/reviews', { params: { page } })

    // Если парсинг в процессе и данных ещё нет
    if (data.status === 'parsing' && (!data.reviews || data.reviews.length === 0)) {
      isParsing.value = true
      loading.value = false
      startPolling()
      return
    }

    if (page === 1) {
      reviews.value = data.reviews || []
    } else {
      reviews.value.push(...(data.reviews || []))
    }

    statistics.value = data.statistics || { average_rating: 0, total: 0 }
    organization.value = data.organization || {}
    cachedAt.value = data.cached_at || null
    isParsing.value = data.is_parsing || false
    isComplete.value = data.is_complete || false

    if (data.pagination) {
      currentPage.value = data.pagination.current_page
      hasMore.value = data.pagination.has_more
      totalReviews.value = data.pagination.total
    }

    // Если парсинг ещё идёт — поллим для обновления счётчика
    if (isParsing.value && !isComplete.value) {
      startPolling()
    } else {
      stopPolling()
    }
  } catch {
    if (page === 1) reviews.value = []
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

/**
 * Поллинг — периодически запрашиваем page=1 чтобы обновить мету и статистику
 */
function startPolling() {
  if (pollingTimer) return
  pollingTimer = setInterval(async () => {
    try {
      const { data } = await api.get('/reviews', { params: { page: 1 } })

      if (data.status === 'parsing' && (!data.reviews || data.reviews.length === 0)) {
        // Ещё даже первая страница не готова
        return
      }

      // Данные появились
      isParsing.value = data.is_parsing || false
      isComplete.value = data.is_complete || false
      statistics.value = data.statistics || statistics.value
      organization.value = data.organization || organization.value
      cachedAt.value = data.cached_at || cachedAt.value

      if (data.pagination) {
        totalReviews.value = data.pagination.total
        // Обновляем hasMore для текущей страницы
        hasMore.value = currentPage.value < (data.pagination.last_page || 1)
      }

      // Если отзывов ещё не было — подставляем первую страницу
      if (reviews.value.length === 0 && data.reviews && data.reviews.length > 0) {
        reviews.value = data.reviews
        currentPage.value = 1
      }

      // Парсинг завершён — останавливаем поллинг
      if (!isParsing.value || isComplete.value) {
        stopPolling()
      }
    } catch {
      // Молча игнорируем ошибки поллинга
    }
  }, 5000)
}

function stopPolling() {
  if (pollingTimer) {
    clearInterval(pollingTimer)
    pollingTimer = null
  }
}

async function loadMore() {
  if (loadingMore.value || !hasMore.value) return
  await fetchReviews(currentPage.value + 1)
}

function onWindowScroll() {
  if (loadingMore.value || !hasMore.value) return
  const distanceToBottom = document.documentElement.scrollHeight - window.scrollY - window.innerHeight
  if (distanceToBottom < 400) loadMore()
}

onMounted(() => {
  fetchReviews(1)
  window.addEventListener('scroll', onWindowScroll, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('scroll', onWindowScroll)
  stopPolling()
})

function formatDate(dateStr) {
  if (!dateStr) return ''
  if (/^\d{1,2}\s/.test(dateStr)) return dateStr
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <div class="reviews-page">
    <div v-if="loading" class="loading">
      <div class="loading-spinner"></div>
      <span>Загрузка отзывов...</span>
    </div>

    <!-- Парсинг в процессе, данных ещё нет -->
    <div v-else-if="isParsing && reviews.length === 0" class="loading">
      <div class="loading-spinner"></div>
      <span>Идёт загрузка отзывов с Яндекс Карт...</span>
      <span class="loading-hint">Первые отзывы появятся через несколько секунд</span>
    </div>

    <template v-else>
      <div class="reviews-content">
        <div class="reviews-source">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="#ef4444">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z" />
          </svg>
          Яндекс Карты
          <span v-if="organization.name" class="org-name">&middot; {{ organization.name }}</span>
          <span v-if="isParsing && !isComplete" class="parsing-badge">⏳ загрузка...</span>
        </div>

        <div class="reviews-list">
          <div v-for="review in reviews" :key="review.id" class="review-card">
            <div class="review-header">
              <span class="review-date">{{ formatDate(review.published_at) }}</span>
            </div>
            <div class="review-author">
              <span class="author-name">{{ review.author }}</span>
            </div>
            <div class="review-stars">
              <svg v-for="i in 5" :key="i" width="16" height="16" viewBox="0 0 24 24"
                :fill="i <= review.rating ? '#f59e0b' : '#e0e4ea'">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
              </svg>
            </div>
            <p v-if="review.text" class="review-text">{{ review.text }}</p>
          </div>
        </div>

        <div v-if="loadingMore" class="loading-more">
          <div class="loading-spinner small"></div>
          <span>Загрузка ещё отзывов...</span>
        </div>

        <div v-if="!loadingMore && reviews.length > 0" class="reviews-counter">
          Показано {{ reviews.length }} из {{ totalReviews }}
          <span v-if="isParsing && !isComplete" class="parsing-note">(ещё загружаются...)</span>
          <button v-if="hasMore" class="load-more-btn" @click="loadMore">Загрузить ещё</button>
        </div>

        <div v-if="!reviews.length" class="reviews-empty">Отзывов пока нет</div>
      </div>

      <div class="reviews-sidebar">
        <div class="stats-card">
          <div class="stats-row">
            <div class="stats-rating">{{ statistics.average_rating.toFixed(1) }}</div>
            <div class="stats-stars">
            <svg v-for="i in 5" :key="i" width="22" height="22" viewBox="0 0 24 24"
              :fill="i <= Math.round(statistics.average_rating) ? '#f59e0b' : '#e0e4ea'">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
            </svg>
            </div>
          </div>
          <div class="stats-total">Всего отзывов: {{ statistics.total.toLocaleString('ru-RU') }}</div>

        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.reviews-page { display: flex; gap: 24px; align-items: flex-start; }
.reviews-content { flex: 1; min-width: 0; }
.reviews-source { display: flex; align-items: center; gap: 6px; font-size: 14px; color: var(--color-text); margin-bottom: 16px; }
.org-name { color: var(--color-text-muted); }
.parsing-badge { background: #fef3c7; color: #92400e; font-size: 11px; padding: 2px 8px; border-radius: 9999px; margin-left: 8px; }
.parsing-note { color: #92400e; font-size: 12px; }
.reviews-list { display: flex; flex-direction: column; gap: 12px; }
.review-card { background: #fff; border: 1px solid var(--color-border-light); border-radius: 10px; padding: 20px 24px; }
.review-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.review-date { font-size: 13px; color: var(--color-text-muted); }
.review-author { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.author-name { font-size: 14px; font-weight: 500; color: var(--color-text); }
.review-stars { display: flex; gap: 2px; margin-bottom: 10px; }
.review-text { font-size: 14px; line-height: 1.6; color: var(--color-text-secondary); margin: 0; }
.reviews-sidebar { width: 200px; min-width: 200px; }
.stats-card { background: #fff; border: 1px solid var(--color-border-light); border-radius: 10px; padding: 24px; text-align: center; position: sticky; top: 24px; }
.stats-row { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 12px; }
.stats-rating { font-size: 36px; font-weight: 600; color: var(--color-text); line-height: 1; }
.stats-stars { display: flex; align-items: center; gap: 2px; }
.stats-total { font-size: 13px; color: var(--color-text-muted); margin-bottom: 16px; }
.reviews-empty { text-align: center; padding: 48px 0; color: var(--color-text-muted); font-size: 14px; }
.loading { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 48px 0; color: var(--color-text-muted); width: 100%; }
.loading-hint { font-size: 12px; color: var(--color-text-muted); opacity: 0.7; }
.loading-more { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 24px 0; color: var(--color-text-muted); font-size: 13px; }
.loading-spinner { width: 32px; height: 32px; border: 3px solid var(--color-border-light); border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; }
.loading-spinner.small { width: 18px; height: 18px; border-width: 2px; }
@keyframes spin { to { transform: rotate(360deg); } }
.reviews-counter { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 20px 0; font-size: 13px; color: var(--color-text-muted); }
.load-more-btn { background: none; border: 1px solid var(--color-border-light); border-radius: 6px; padding: 6px 14px; font-size: 13px; color: #6366f1; cursor: pointer; transition: all 0.15s; }
.load-more-btn:hover { background: #f5f3ff; border-color: #6366f1; }
</style>
