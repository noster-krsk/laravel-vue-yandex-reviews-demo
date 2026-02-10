import { ref } from 'vue'
import { defineStore } from 'pinia'
import api, { getCsrfCookie } from '@/api/axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const isAuthenticated = ref(!!localStorage.getItem('auth_authenticated'))
  const loading = ref(false)
  const errors = ref({})

  async function fetchUser() {
    try {
      const { data } = await api.get('/user')
      user.value = data
      isAuthenticated.value = true
      localStorage.setItem('auth_authenticated', '1')
    } catch {
      user.value = null
      isAuthenticated.value = false
      localStorage.removeItem('auth_authenticated')
    }
  }

  async function login(credentials) {
    errors.value = {}
    loading.value = true
    try {
      await getCsrfCookie()
      await api.post('/login', credentials)
      await fetchUser()
      return true
    } catch (e) {
      if (e.response?.status === 422) {
        errors.value = e.response.data.errors || {}
      } else {
        errors.value = { email: ['Неверный email или пароль'] }
      }
      return false
    } finally {
      loading.value = false
    }
  }

  async function register(data) {
    errors.value = {}
    loading.value = true
    try {
      await getCsrfCookie()
      await api.post('/register', data)
      await fetchUser()
      return true
    } catch (e) {
      if (e.response?.status === 422) {
        errors.value = e.response.data.errors || {}
      } else {
        errors.value = { email: ['Ошибка регистрации'] }
      }
      return false
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      user.value = null
      isAuthenticated.value = false
      localStorage.removeItem('auth_authenticated')
    }
  }

  return { user, isAuthenticated, loading, errors, fetchUser, login, register, logout }
})
