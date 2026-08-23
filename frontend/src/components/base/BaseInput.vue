<script setup>
defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, required: true },
  type: { type: String, default: 'text' },
  error: { type: String, default: '' },
  autocomplete: { type: String, default: 'off' },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div>
    <label class="mb-1 block text-sm font-medium text-ink-900">{{ label }}</label>
    <div class="relative">
      <div v-if="$slots.icon" class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
        <slot name="icon" />
      </div>
      <input
        :type="type"
        :value="modelValue"
        :autocomplete="autocomplete"
        class="w-full rounded-lg border px-3 py-2 text-sm text-ink-900 shadow-sm outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
        :class="[error ? 'border-red-400' : 'border-line-300', $slots.icon ? 'pl-12' : '', $slots.trailing ? 'pr-12' : '']"
        @input="$emit('update:modelValue', $event.target.value)"
      />
      <div v-if="$slots.trailing" class="absolute inset-y-0 right-0 flex items-center pr-4">
        <slot name="trailing" />
      </div>
    </div>
    <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
  </div>
</template>
