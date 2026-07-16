export const QUOTATION_STATUS_STATS_KEYS: Record<string, string> = {
  'Requested for Quotation': 'requested',
  'Drafted': 'drafted',
  'Sent': 'sent',
  'Awaiting Customer Response': 'awaiting',
  'Under Negotiation': 'negotiation',
  'Approved': 'approved',
  'Accepted': 'accepted',
  'Rejected': 'rejected',
}

export const TERMINAL_QUOTATION_STATUSES = ['Accepted', 'Rejected'] as const

export function isTerminalQuotationStatus(statusName?: string | null): boolean {
  if (!statusName) return false
  return (TERMINAL_QUOTATION_STATUSES as readonly string[]).includes(statusName)
}
