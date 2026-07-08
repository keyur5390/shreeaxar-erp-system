import { createCrudService } from './crud'
import type { User } from '@/types'

export const usersService = createCrudService<User>('/users')
