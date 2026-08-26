<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
})

// Small delay before showing, so a fast transition doesn't flash the overlay.
const visible = ref(false)
let showTimer = null

watch(
  () => props.show,
  (show) => {
    clearTimeout(showTimer)
    if (show) {
      showTimer = setTimeout(() => {
        visible.value = true
      }, 150)
    } else {
      visible.value = false
    }
  },
)

onBeforeUnmount(() => clearTimeout(showTimer))
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-150"
    leave-active-class="transition-opacity duration-150"
    enter-from-class="opacity-0"
    leave-to-class="opacity-0"
  >
    <div
      v-if="visible"
      class="fixed inset-0 z-50 flex items-center justify-center bg-white/60 backdrop-blur-sm"
    >
      <svg class="h-10 w-10 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>
  </Transition>
</template>
