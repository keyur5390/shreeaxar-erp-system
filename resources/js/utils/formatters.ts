const dateFormatter = new Intl.DateTimeFormat('en-GB')
const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })

export function formatCurrency(amount: number): string {
  return `RWF ${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Math.round(amount || 0))}`
}

export function formatDate(date: string | Date): string { return dateFormatter.format(new Date(date)) }
export function formatDateTime(date: string | Date): string { return dateTimeFormatter.format(new Date(date)).replace(',', '') }

export function getInitials(firstName?: string | null, lastName?: string | null): string {
  const parts = [firstName, lastName].flatMap((value) => (value || '').trim().split(/\s+/).filter(Boolean))
  if (parts.length === 0) return ''
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase()
}

export function maskAccountNumber(account?: string | null): string { const value = account || ''; return value.length <= 4 ? value : `****${value.slice(-4)}` }

export function daysUntil(date: string | Date): number {
  const target = new Date(date); const today = new Date()
  const targetUtc = Date.UTC(target.getFullYear(), target.getMonth(), target.getDate())
  const todayUtc = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate())
  return Math.round((targetUtc - todayUtc) / 86_400_000)
}

export function abbreviateCurrency(amount: number): string {
  const rounded = Math.round(amount || 0)
  if (Math.abs(rounded) >= 1_000_000) return `RWF ${(rounded / 1_000_000).toFixed(1).replace(/\.0$/, '')}M`
  if (Math.abs(rounded) >= 1_000) return `RWF ${(rounded / 1_000).toFixed(1).replace(/\.0$/, '')}K`
  return formatCurrency(rounded)
}
