<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'

const reviews = ref([])
const stats = ref({ average_rating: 0, total_count: 0 })
const loading = ref(true)

async function fetchReviews() {
  loading.value = true
  try {
    const { data } = await api.get('/reviews')
    reviews.value = data.reviews || []
    stats.value = data.stats || { average_rating: 0, total_count: 0 }
  } catch {
    reviews.value = []
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }) + ' ' + d.toLocaleTimeString('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(fetchReviews)
</script>

<template>
  <div class="reviews-page">
    <div v-if="loading" class="loading">Загрузка...</div>

    <template v-else>
      <div class="reviews-content">
        <div class="reviews-source">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="#ef4444">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z" />
          </svg>
          Яндекс Карты
        </div>

        <div class="reviews-list">
          <div v-for="review in reviews" :key="review.id" class="review-card">
            <div class="review-header">
              <span class="review-date">{{ formatDate(review.date) }}</span>
              <span v-if="review.branch" class="review-branch">
                {{ review.branch }}
                <svg width="12" height="12" viewBox="0 0 24 24" fill="#ef4444">
                  <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z" />
                </svg>
              </span>
            </div>
            <div class="review-author">
              <span class="author-name">{{ review.author }}</span>
              <span v-if="review.phone" class="author-phone">{{ review.phone }}</span>
            </div>
            <div class="review-stars">
              <svg
                v-for="i in 5"
                :key="i"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                :fill="i <= review.rating ? '#f59e0b' : '#e0e4ea'"
              >
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
              </svg>
            </div>
            <p class="review-text">{{ review.text }}</p>
          </div>
        </div>

        <div v-if="!reviews.length" class="reviews-empty">
          Отзывов пока нет
        </div>
      </div>

      <div class="reviews-sidebar">
        <div class="stats-card">
          <div class="stats-rating">{{ stats.average_rating.toFixed(1) }}</div>
          <div class="stats-stars">
            <svg
              v-for="i in 5"
              :key="i"
              width="22"
              height="22"
              viewBox="0 0 24 24"
              :fill="i <= Math.round(stats.average_rating) ? '#f59e0b' : '#e0e4ea'"
            >
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
            </svg>
          </div>
          <div class="stats-total">Всего отзывов: {{ stats.total_count.toLocaleString('ru-RU') }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.reviews-page {
  display: flex;
  gap: 24px;
  align-items: flex-start;
}

.reviews-content {
  flex: 1;
  min-width: 0;
}

.reviews-source {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: var(--color-text);
  margin-bottom: 16px;
}

.reviews-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.review-card {
  background: #fff;
  border: 1px solid var(--color-border-light);
  border-radius: 10px;
  padding: 20px 24px;
}

.review-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.review-date {
  font-size: 13px;
  color: var(--color-text-muted);
}

.review-branch {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.review-author {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.author-name {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
}

.author-phone {
  font-size: 13px;
  color: var(--color-text-muted);
}

.review-stars {
  display: flex;
  gap: 2px;
  margin-bottom: 10px;
}

.review-text {
  font-size: 14px;
  line-height: 1.6;
  color: var(--color-text-secondary);
}

.reviews-sidebar {
  width: 200px;
  min-width: 200px;
}

.stats-card {
  background: #fff;
  border: 1px solid var(--color-border-light);
  border-radius: 10px;
  padding: 24px;
  text-align: center;
  position: sticky;
  top: 24px;
}

.stats-rating {
  font-size: 36px;
  font-weight: 600;
  color: var(--color-text);
  line-height: 1;
  margin-bottom: 8px;
}

.stats-stars {
  display: flex;
  justify-content: center;
  gap: 2px;
  margin-bottom: 12px;
}

.stats-total {
  font-size: 13px;
  color: var(--color-text-muted);
}

.reviews-empty {
  text-align: center;
  padding: 48px 0;
  color: var(--color-text-muted);
  font-size: 14px;
}

.loading {
  text-align: center;
  padding: 48px 0;
  color: var(--color-text-muted);
}
</style>
