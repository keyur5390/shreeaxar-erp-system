<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth.store'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const redirectTo = computed(() => typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard')
function safeRedirect(path: string) { return path.startsWith('/') && !path.startsWith('//') ? path : '/dashboard' }
function demoLogin() { authStore.setToken('demo-token'); router.push(safeRedirect(redirectTo.value)) }
</script>
<template><AuthLayout><div class="space-y-4"><button class="w-full rounded-xl bg-[#1F4E79] px-4 py-3 font-semibold text-white" @click="demoLogin">Sign in</button></div></AuthLayout></template>
