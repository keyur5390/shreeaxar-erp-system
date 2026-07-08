import { createCrudService } from './crud'
import type { Product } from '@/types'

export const productsService = createCrudService<Product>('/products')
