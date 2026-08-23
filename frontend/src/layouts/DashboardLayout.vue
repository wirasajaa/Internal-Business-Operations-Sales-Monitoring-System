<script setup>
import { provide, ref } from 'vue'
import Navbar from '../components/layout/Navbar.vue'
import Sidebar from '../components/layout/Sidebar.vue'

const sidebarOpen = ref(false)
provide('sidebarOpen', sidebarOpen)
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-slate-50">
    <Sidebar />
    <div class="flex min-w-0 flex-1 flex-col">
      <Navbar />
      <main class="flex-1 overflow-y-auto p-4 sm:p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
