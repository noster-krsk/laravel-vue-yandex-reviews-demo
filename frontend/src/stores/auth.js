import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/api/axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const isAuthenticated = ref(!!localStorage.getItem('auth_token'))
  const loading = ref(false)
  const errors = ref({})

  async function fetchUser() {
    try {
      const { data } = await api.get('/user')
      user.value = data
      isAuthenticated.value = true
    } catch {
      user.value = null
      isAuthenticated.value = false
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_authenticated')
    }
  }

  function setAuth(data) {
    localStorage.setItem('auth_token', data.access_token)
    localStorage.setItem('auth_authenticated', '1')
    user.value = data.user
    isAuthenticated.value = true
  }

  async function login(credentials) {
    errors.value = {}
    loading.value = true
    try {
      const { data } = await api.post('/login', credentials)
      setAuth(data)
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

  async function register(payload) {
    errors.value = {}
    loading.value = true
    try {
      const { data } = await api.post('/register', payload)
      setAuth(data)
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
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_authenticated')
    }
  }

  return { user, isAuthenticated, loading, errors, fetchUser, login, register, logout }
})
