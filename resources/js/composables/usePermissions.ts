import type { PermissionMap } from '@/types'
export function usePermissions(permissions: PermissionMap = {}) { return { can: (permission: string) => Boolean(permissions[permission]) } }
