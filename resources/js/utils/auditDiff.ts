export function collectJsonKeys(value: unknown): Set<string> {
  if (value === null || value === undefined || typeof value !== 'object' || Array.isArray(value)) {
    return new Set()
  }

  return new Set(Object.keys(value as Record<string, unknown>))
}

export function getChangedJsonKeys(
  oldValues: Record<string, unknown> | null | undefined,
  newValues: Record<string, unknown> | null | undefined,
): Set<string> {
  const oldKeys = collectJsonKeys(oldValues)
  const newKeys = collectJsonKeys(newValues)
  const changed = new Set<string>()

  for (const key of new Set([...oldKeys, ...newKeys])) {
    const oldVal = oldValues?.[key]
    const newVal = newValues?.[key]

    if (JSON.stringify(oldVal) !== JSON.stringify(newVal)) {
      changed.add(key)
    }
  }

  return changed
}

export function formatJsonLines(
  value: Record<string, unknown> | null | undefined,
  changedKeys: Set<string>,
): Array<{ key: string; value: string; changed: boolean }> {
  if (!value || typeof value !== 'object') {
    return []
  }

  return Object.entries(value).map(([key, entryValue]) => ({
    key,
    value: JSON.stringify(entryValue, null, 2),
    changed: changedKeys.has(key),
  }))
}
