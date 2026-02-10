import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = document.cookie
    .split('; ')
    .find((row) => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1]

  if (token) {
    config.headers['X-XSRF-TOKEN'] = decodeURIComponent(token)
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_authenticated')
      window.location.href = '/dist/login'
    }
    return Promise.reject(error)
  },
)

export async function getCsrfCookie() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

export default api
