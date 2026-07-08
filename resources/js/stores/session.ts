import { defineStore } from 'pinia'
export const useSessionStore = defineStore('session', { state: () => ({ appName: import.meta.env.VITE_APP_NAME ?? 'Shree Axar ERP', idleMinutes: Number(import.meta.env.VITE_SESSION_IDLE_MINUTES ?? 30) }) })
