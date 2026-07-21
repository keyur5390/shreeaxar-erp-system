import api from './api'
import { unwrap } from './crud'
import type {
  PaginatedItems,
  ProductDeleteResult,
  ProductDetail,
  ProductDuplicateResult,
  ProductImage,
  ProductListItem,
  ProductListParams,
  ProductPayload,
  ProductSearchResult,
} from '@/types'

const uploadConfig = { timeout: 120000 }

function buildProductFormData(
  payload: ProductPayload,
  options?: {
    primaryImage?: File | null
    galleryImages?: File[]
    keepImageIds?: string[]
    removePrimaryImage?: boolean
  },
): FormData {
  const formData = new FormData()

  formData.append('title', payload.title)
  formData.append('rate', String(payload.rate))
  formData.append('unit_id', payload.unit_id)

  if (payload.model_number) {
    formData.append('model_number', payload.model_number)
  }

  if (payload.description) {
    formData.append('description', payload.description)
  }

  if (payload.currency_id) {
    formData.append('currency_id', payload.currency_id)
  }

  if (payload.is_tax_included !== undefined) {
    formData.append('is_tax_included', payload.is_tax_included ? '1' : '0')
  }

  if (payload.is_active !== undefined) {
    formData.append('is_active', payload.is_active ? '1' : '0')
  }

  if (options?.primaryImage instanceof File) {
    formData.append('primaryImage', options.primaryImage)
  }

  options?.galleryImages?.forEach((file) => {
    formData.append('images[]', file, file.name)
  })

  if (options?.removePrimaryImage) {
    formData.append('remove_primary_image', '1')
  }

  if (options?.keepImageIds !== undefined) {
    formData.append('keep_image_ids', JSON.stringify(options.keepImageIds))
  }

  return formData
}

export const productsService = {
  async list(params?: ProductListParams): Promise<PaginatedItems<ProductListItem>> {
    const query: Record<string, string | number | boolean> = {}
    if (params?.search) query.search = params.search
    if (params?.unit_id) query.unit_id = params.unit_id
    if (params?.is_active !== undefined && params?.is_active !== '') query.is_active = params.is_active
    if (params?.page) query.page = params.page
    if (params?.grid) query.grid = 1
    return unwrap((await api.get('/products', { params: query })).data)
  },

  async get(id: string): Promise<ProductDetail> {
    return unwrap((await api.get(`/products/${id}`)).data)
  },

  async create(
    payload: ProductPayload,
    primaryImage?: File | null,
    galleryImages?: File[],
  ): Promise<ProductDetail> {
    const formData = buildProductFormData(payload, { primaryImage, galleryImages })
    return unwrap((await api.post('/products', formData, uploadConfig)).data)
  },

  async update(
    id: string,
    payload: ProductPayload,
    options?: {
      primaryImage?: File | null
      galleryImages?: File[]
      keepImageIds?: string[]
      removePrimaryImage?: boolean
    },
  ): Promise<ProductDetail> {
    const formData = buildProductFormData(payload, options)
    formData.append('_method', 'PUT')
    return unwrap((await api.post(`/products/${id}`, formData, uploadConfig)).data)
  },

  async remove(id: string): Promise<ProductDeleteResult> {
    return unwrap((await api.delete(`/products/${id}`)).data)
  },

  async toggleStatus(id: string): Promise<{ id: string; is_active: boolean }> {
    return unwrap((await api.patch(`/products/${id}/toggle-status`)).data)
  },

  async reorderImages(id: string, items: Array<{ id: string; sort_order: number }>): Promise<ProductImage[]> {
    return unwrap((await api.patch(`/products/${id}/reorder-images`, items)).data)
  },

  async duplicate(id: string): Promise<ProductDuplicateResult> {
    return unwrap((await api.post(`/products/${id}/duplicate`)).data)
  },

  async search(q: string, limit = 10): Promise<ProductSearchResult[]> {
    return unwrap((await api.get('/products/search', { params: { q, limit } })).data)
  },

  async checkModel(modelNumber: string, excludeId?: string): Promise<{
    available: boolean
    existing_product?: { id: string; title: string }
  }> {
    return unwrap((await api.get('/products/check-model', {
      params: { model_number: modelNumber, exclude_id: excludeId },
    })).data)
  },
}
