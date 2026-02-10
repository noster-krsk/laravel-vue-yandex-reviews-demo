<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const mode = ref('login')
const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const isLogin = computed(() => mode.value === 'login')

function switchMode(m) {
  mode.value = m
  auth.errors = {}
  form.value = { name: '', email: '', password: '', password_confirmation: '' }
}

async function submit() {
  let success
  if (isLogin.value) {
    success = await auth.login({
      email: form.value.email,
      password: form.value.password,
    })
  } else {
    success = await auth.register(form.value)
  }
  if (success) {
    router.push({ name: 'reviews' })
  }
}
</script>

<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-logo">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
          <path d="M13 3L4 14h7l-2 7 9-11h-7l2-7z" fill="#4F6EF7" />
        </svg>
        <span>Daily Grow</span>
      </div>

      <div class="auth-tabs">
        <button :class="{ active: isLogin }" @click="switchMode('login')">Вход</button>
        <button :class="{ active: !isLogin }" @click="switchMode('register')">
          Регистрация
        </button>
      </div>

      <form @submit.prevent="submit">
        <div v-if="!isLogin" class="form-group">
          <label>Имя</label>
          <input v-model="form.name" type="text" placeholder="Ваше имя" />
          <span v-if="auth.errors.name" class="error">{{ auth.errors.name[0] }}</span>
        </div>

        <div class="form-group">
          <label>Email</label>
          <input v-model="form.email" type="email" placeholder="email@example.com" />
          <span v-if="auth.errors.email" class="error">{{ auth.errors.email[0] }}</span>
        </div>

        <div class="form-group">
          <label>Пароль</label>
          <input v-model="form.password" type="password" placeholder="Введите пароль" />
          <span v-if="auth.errors.password" class="error">{{ auth.errors.password[0] }}</span>
        </div>

        <div v-if="!isLogin" class="form-group">
          <label>Подтверждение пароля</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            placeholder="Повторите пароль"
          />
        </div>

        <button type="submit" class="btn-submit" :disabled="auth.loading">
          {{ auth.loading ? 'Загрузка...' : isLogin ? 'Войти' : 'Зарегистрироваться' }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f7fb;
}

.auth-card {
  background: #fff;
  border-radius: 12px;
  padding: 40px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.auth-logo {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 20px;
  font-weight: 600;
  color: #1a1a2e;
  margin-bottom: 32px;
}

.auth-tabs {
  display: flex;
  gap: 0;
  margin-bottom: 24px;
  border-bottom: 2px solid #e8ecf1;
}

.auth-tabs button {
  flex: 1;
  padding: 10px 0;
  background: none;
  border: none;
  font-size: 15px;
  color: #9ca3af;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
}

.auth-tabs button.active {
  color: #4f6ef7;
  border-bottom-color: #4f6ef7;
  font-weight: 500;
}

.form-group {
  margin-bottom: 18px;
}

.form-group label {
  display: block;
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 6px;
}

.form-group input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #e0e4ea;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
  background: #fff;
  color: #1a1a2e;
}

.form-group input:focus {
  border-color: #4f6ef7;
}

.form-group input::placeholder {
  color: #c5c9d2;
}

.error {
  display: block;
  color: #ef4444;
  font-size: 12px;
  margin-top: 4px;
}

.btn-submit {
  width: 100%;
  padding: 12px;
  background: #4f6ef7;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
  margin-top: 8px;
}

.btn-submit:hover {
  background: #3d5bd9;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
