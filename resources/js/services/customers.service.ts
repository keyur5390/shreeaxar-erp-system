import { createCrudService } from './crud'
import type { Customer } from '@/types'

export const customersService = createCrudService<Customer>('/customers')
