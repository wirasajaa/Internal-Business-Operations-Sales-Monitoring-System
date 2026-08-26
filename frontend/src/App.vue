<script setup>
import { useUiStore } from './stores/ui'
import BaseLoadingOverlay from './components/base/BaseLoadingOverlay.vue'

const ui = useUiStore()
</script>

<template>
  <router-view />
  <BaseLoadingOverlay :show="ui.isLoading" />
</template>
