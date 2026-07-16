import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import type { QuotationExpirySummary } from '@/types'

interface NotificationReadState {
  readAt: string
  expiringToday: number
  expiringSoon: number
}

function readKey(userId: string): string {
  return `shreeaxar_notifications_read_${userId}`
}

export function useNotifications() {
  const authStore = useAuthStore()
  const readState = ref<NotificationReadState | null>(null)

  function loadReadState(): void {
    const userId = authStore.user?.id
    if (!userId) {
      readState.value = null
      return
    }

    try {
      const raw = localStorage.getItem(readKey(userId))
      readState.value = raw ? JSON.parse(raw) as NotificationReadState : null
    } catch {
      readState.value = null
    }
  }

  function markAsRead(summary: QuotationExpirySummary): void {
    const userId = authStore.user?.id
    if (!userId) return

    const next: NotificationReadState = {
      readAt: new Date().toISOString(),
      expiringToday: summary.expiring_today,
      expiringSoon: summary.expiring_soon_7d,
    }

    localStorage.setItem(readKey(userId), JSON.stringify(next))
    readState.value = next
  }

  function unreadCount(summary: QuotationExpirySummary | undefined): number {
    loadReadState()

    if (!summary) return 0

    const total = summary.expiring_today + summary.expiring_soon_7d
    if (!readState.value) return total

    const newToday = Math.max(0, summary.expiring_today - readState.value.expiringToday)
    const newSoon = Math.max(0, summary.expiring_soon_7d - readState.value.expiringSoon)

    return newToday + newSoon
  }

  const hasUnread = computed(() => Boolean(readState.value))

  return {
    readState,
    hasUnread,
    loadReadState,
    markAsRead,
    unreadCount,
  }
}
