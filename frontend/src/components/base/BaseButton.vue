<script setup>
defineProps({
  type: { type: String, default: 'button' },
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const variantClasses = {
  primary: 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600',
  secondary: 'bg-white text-ink-600 border border-line-300 hover:bg-slate-50 focus-visible:outline-slate-400',
  ghost: 'text-ink-600 hover:bg-slate-100 focus-visible:outline-slate-400',
}

const sizeClasses = {
  md: 'rounded-md px-4 py-2 text-sm',
  lg: 'rounded-lg px-6 py-[17px] text-base',
}
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center gap-2 font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    :class="[variantClasses[variant], sizeClasses[size]]"
  >
    <span
      v-if="loading"
      class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>
