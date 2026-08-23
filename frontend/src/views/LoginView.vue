<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import BaseInput from '../components/base/BaseInput.vue'
import BaseButton from '../components/base/BaseButton.vue'
import BaseAlert from '../components/base/BaseAlert.vue'
import mailIcon from '../assets/icons/mail.svg'
import lockIcon from '../assets/icons/lock.svg'
import eyeIcon from '../assets/icons/eye.svg'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ email: '', password: '' })
const fieldErrors = reactive({ email: '', password: '' })
const apiError = ref('')
const loading = ref(false)
const showPassword = ref(false)

function validate() {
  fieldErrors.email = form.email ? '' : 'Email wajib diisi.'
  fieldErrors.password = form.password ? '' : 'Password wajib diisi.'
  return !fieldErrors.email && !fieldErrors.password
}

async function handleSubmit() {
  apiError.value = ''
  if (!validate()) return

  loading.value = true
  try {
    await auth.login(form.email, form.password)
    router.push({ name: 'dashboard.home' })
  } catch (error) {
    const status = error.response?.status
    apiError.value =
      status === 403
        ? 'Akun tidak aktif. Hubungi administrator.'
        : 'Email atau password salah.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div
    class="flex min-h-screen flex-col items-stretch justify-between"
    style="background-image: linear-gradient(147deg, #eceff0 0%, #d5e3fd 100%)"
  >
    <div class="flex flex-1 items-center justify-center p-4 sm:p-6">
      <div class="w-full max-w-[448px] rounded-xl border border-line-200 bg-white shadow-lg">
        <div class="p-6 sm:p-12">
          <div class="flex flex-col items-center gap-1 pb-8 text-center">
            <span class="material-symbols-outlined text-[48px]! text-brand-600">corporate_fare</span>
            <h1 class="pt-3 text-3xl font-semibold text-ink-900">Login</h1>
            <p class="text-sm text-ink-600">Masukkan kredensial Anda untuk mengakses portal manajemen.</p>
          </div>

          <form class="flex flex-col gap-6" @submit.prevent="handleSubmit">
            <BaseAlert v-if="apiError" variant="error">{{ apiError }}</BaseAlert>

            <BaseInput
              v-model="form.email"
              label="Email Address"
              type="email"
              autocomplete="username"
              :error="fieldErrors.email"
            >
              <template #icon><img :src="mailIcon" alt="" class="h-4 w-5" /></template>
            </BaseInput>

            <BaseInput
              v-model="form.password"
              label="Password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              :error="fieldErrors.password"
            >
              <template #icon><img :src="lockIcon" alt="" class="h-[21px] w-4" /></template>
              <template #trailing>
                <button type="button" class="text-ink-600" @click="showPassword = !showPassword">
                  <img :src="eyeIcon" alt="Tampilkan password" class="h-[15px] w-[22px]" />
                </button>
              </template>
            </BaseInput>

            <BaseButton type="submit" size="lg" class="w-full" :loading="loading">
              Sign In
            </BaseButton>
          </form>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-center gap-2 p-4 text-center text-xs text-ink-500 sm:gap-4 sm:p-6">
      <span class="font-bold text-ink-900">ERP Internal</span>
      <span>© 2026 Enterprise Management Systems. Hak cipta dilindungi.</span>
    </div>
  </div>
</template>
