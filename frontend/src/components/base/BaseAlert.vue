<script setup>
defineProps({
  variant: { type: String, default: 'error' },
})

const variantClasses = {
  error: 'bg-red-50 text-red-700 border-red-200',
  success: 'bg-emerald-50 text-emerald-700 border-emerald-200',
}
</script>

<template>
  <div class="rounded-md border px-3 py-2 text-sm" :class="variantClasses[variant]">
    <slot />
  </div>
</template>
