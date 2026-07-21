<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useForm, ErrorMessage, Field } from 'vee-validate'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useDebounceFn } from '@vueuse/core'
import { ImagePlus, Upload, X } from 'lucide-vue-next'
import * as yup from 'yup'
import PageHeader from '@/components/ui/PageHeader.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import { productsService } from '@/services/products.service'
import { unitsService } from '@/services/units.service'
import { useCurrencies } from '@/composables/useCurrencies'
import { useToast } from '@/composables/useToast'
import { useFormDirtyGuard } from '@/composables/useFormDirtyGuard'
import { ValidationError } from '@/services/api'
import type { ProductPayload } from '@/types'

const MAX_GALLERY = 8
const IMAGE_EXTENSIONS = /\.(jpe?g|png|webp)$/i

type GalleryItem =
  | { key: string; type: 'existing'; id: string; image_url: string }
  | { key: string; type: 'new'; file: File; preview: string }

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()

const productId = computed(() => {
  const id = route.params.id
  return typeof id === 'string' && id !== 'new' ? id : undefined
})
const isEdit = computed(() => Boolean(productId.value))

const submitError = ref('')
const primaryImageFile = ref<File | null>(null)
const primaryPreview = ref<string | null>(null)
const removePrimaryImage = ref(false)
const galleryImages = ref<GalleryItem[]>([])
const skipReorder = ref(true)
const initialExistingImageCount = ref(0)
const primaryInput = ref<HTMLInputElement | null>(null)
const galleryInput = ref<HTMLInputElement | null>(null)
const modelWarning = ref<{ title: string } | null>(null)
const primaryDragOver = ref(false)
const galleryDragOver = ref(false)

let modelCheckTimer: ReturnType<typeof setTimeout> | null = null

async function checkModelDuplicate(modelNumber: string): Promise<void> {
  if (!modelNumber.trim()) {
    modelWarning.value = null
    return
  }

  return new Promise((resolve) => {
    if (modelCheckTimer) clearTimeout(modelCheckTimer)
    modelCheckTimer = setTimeout(async () => {
      try {
        const result = await productsService.checkModel(modelNumber.trim(), productId.value)
        modelWarning.value = result.available || !result.existing_product
          ? null
          : { title: result.existing_product.title }
      } catch {
        modelWarning.value = null
      }
      resolve()
    }, 400)
  })
}

const schema = yup.object({
  title: yup.string().required('Title is required.').max(300),
  model_number: yup.string().max(200).nullable(),
  rate: yup.number().typeError('Rate is required.').required('Rate is required.').moreThan(0, 'Rate must be greater than 0.'),
  unit_id: yup.string().required('Unit is required.'),
  currency_id: yup.string().required('Currency is required.'),
  description: yup.string().nullable(),
  is_tax_included: yup.boolean().default(false),
})

const { handleSubmit, resetForm, meta, values, setFieldValue } = useForm({
  validationSchema: schema,
  initialValues: {
    title: '',
    model_number: '',
    rate: null as number | null,
    unit_id: '',
    currency_id: '',
    description: '',
    is_tax_included: false,
  },
})

const { allowNavigation } = useFormDirtyGuard(computed(() => meta.value.dirty || Boolean(primaryImageFile.value) || galleryImages.value.some((item) => item.type === 'new')))

const productQuery = useQuery({
  queryKey: computed(() => ['products', productId.value]),
  queryFn: () => productsService.get(productId.value!),
  enabled: computed(() => isEdit.value),
})

const unitsQuery = useQuery({
  queryKey: ['units'],
  queryFn: () => unitsService.list(),
})

const units = computed(() => unitsQuery.data.value ?? [])
const { activeCurrencies, defaultCurrency } = useCurrencies()

const productCurrency = computed(() =>
  activeCurrencies.value.find((currency) => currency.id === values.currency_id)
  ?? defaultCurrency.value,
)

watch(
  () => defaultCurrency.value,
  (currency) => {
    if (isEdit.value || values.currency_id || !currency) return
    setFieldValue('currency_id', currency.id)
  },
  { immediate: true },
)

watch(
  () => productQuery.data.value,
  (product) => {
    if (!product) return

    resetForm({
      values: {
        title: product.title,
        model_number: product.model_number ?? '',
        rate: product.rate,
        unit_id: product.unit_id,
        currency_id: product.currency_id,
        description: product.description ?? '',
        is_tax_included: product.is_tax_included ?? false,
      },
    })

    primaryPreview.value = product.primary_image_url
    primaryImageFile.value = null
    removePrimaryImage.value = false

    skipReorder.value = true
    galleryImages.value = product.images.map((image) => ({
      key: image.id,
      type: 'existing' as const,
      id: image.id,
      image_url: image.image_url,
    }))
    initialExistingImageCount.value = product.images.length
    skipReorder.value = false

    if (product.model_number) {
      checkModelDuplicate(product.model_number)
    }
  },
  { immediate: true },
)

watch(
  () => values.model_number,
  (modelNumber) => {
    if (modelNumber) checkModelDuplicate(modelNumber)
    else modelWarning.value = null
  },
)

const galleryCount = computed(() => galleryImages.value.length)
const keepImageIds = computed(() => galleryImages.value
  .filter((item): item is Extract<GalleryItem, { type: 'existing' }> => item.type === 'existing')
  .map((item) => item.id))

const showModelWarning = computed(() => modelWarning.value !== null)

const debouncedReorder = useDebounceFn(async (items: GalleryItem[]) => {
  if (!isEdit.value || !productId.value) return

  const existingInGallery = items.filter((item): item is Extract<GalleryItem, { type: 'existing' }> => item.type === 'existing')
  if (existingInGallery.length !== initialExistingImageCount.value) return

  const payload = items
    .map((item, index) => (item.type === 'existing' ? { id: item.id, sort_order: index } : null))
    .filter((item): item is { id: string; sort_order: number } => item !== null)

  if (!payload.length) return

  try {
    await productsService.reorderImages(productId.value, payload)
    queryClient.invalidateQueries({ queryKey: ['products', productId.value] })
  } catch {
    toast('Unable to save image order.', 'error')
  }
}, 1000)

watch(galleryImages, (items) => {
  if (skipReorder.value) return
  debouncedReorder(items)
}, { deep: true })

function revokePreview(url: string | null) {
  if (url?.startsWith('blob:')) URL.revokeObjectURL(url)
}

function setPrimaryFile(file: File) {
  if (!isImageFile(file)) {
    toast('Please select a JPG, PNG, or WEBP image.', 'error')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    toast('Primary image must be 2MB or smaller.', 'error')
    return
  }
  revokePreview(primaryPreview.value)
  primaryImageFile.value = file
  removePrimaryImage.value = false
  primaryPreview.value = URL.createObjectURL(file)
}

function onPrimarySelect(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) setPrimaryFile(file)
}

function onPrimaryDrop(event: DragEvent) {
  primaryDragOver.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) setPrimaryFile(file)
}

function removePrimary() {
  revokePreview(primaryPreview.value)
  primaryImageFile.value = null
  primaryPreview.value = null
  removePrimaryImage.value = true
}

function isImageFile(file: File): boolean {
  if (file.type.startsWith('image/')) return true
  return IMAGE_EXTENSIONS.test(file.name)
}

function galleryItemKey(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return `new-${crypto.randomUUID()}`
  }
  return `new-${Date.now()}-${Math.random().toString(36).slice(2)}`
}

function addGalleryFiles(files: FileList | File[]) {
  const remaining = MAX_GALLERY - galleryImages.value.length
  if (remaining <= 0) {
    toast(`Maximum ${MAX_GALLERY} gallery images allowed.`, 'error')
    return
  }

  const nextItems: GalleryItem[] = []
  Array.from(files).slice(0, remaining).forEach((file) => {
    if (!isImageFile(file)) return
    if (file.size > 2 * 1024 * 1024) {
      toast(`${file.name} exceeds 2MB and was skipped.`, 'error')
      return
    }
    nextItems.push({
      key: galleryItemKey(),
      type: 'new',
      file,
      preview: URL.createObjectURL(file),
    })
  })

  if (nextItems.length) {
    galleryImages.value = [...galleryImages.value, ...nextItems]
    return
  }

  toast('Please select valid image files (JPG, PNG, or WEBP).', 'error')
}

function onGallerySelect(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files?.length) addGalleryFiles(input.files)
  input.value = ''
}

function onGalleryDrop(event: DragEvent) {
  galleryDragOver.value = false
  if (event.dataTransfer?.files) addGalleryFiles(event.dataTransfer.files)
}

function removeGalleryItem(item: GalleryItem) {
  if (item.type === 'new') revokePreview(item.preview)
  galleryImages.value = galleryImages.value.filter((entry) => entry.key !== item.key)
}

const saveMutation = useMutation({
  mutationFn: async (payload: ProductPayload) => {
    const newGalleryFiles = galleryImages.value
      .filter((item): item is Extract<GalleryItem, { type: 'new' }> => item.type === 'new')
      .map((item) => item.file)

    if (isEdit.value && productId.value) {
      return productsService.update(productId.value, payload, {
        primaryImage: primaryImageFile.value,
        galleryImages: newGalleryFiles,
        keepImageIds: keepImageIds.value,
        removePrimaryImage: removePrimaryImage.value,
      })
    }

    return productsService.create(payload, primaryImageFile.value, newGalleryFiles)
  },
  onSuccess: (product) => {
    allowNavigation()
    queryClient.invalidateQueries({ queryKey: ['products'] })
    toast(isEdit.value ? 'Product updated successfully.' : 'Product created successfully.', 'success')
    router.push(`/products/${product.id}`)
  },
  onError: (error: unknown) => {
    submitError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to save product.'
  },
})

const onSubmit = handleSubmit((formValues) => {
  submitError.value = ''
  if (formValues.rate === null) return

  saveMutation.mutate({
    title: formValues.title,
    model_number: formValues.model_number || null,
    rate: formValues.rate,
    currency_id: formValues.currency_id,
    unit_id: formValues.unit_id,
    description: formValues.description || null,
    is_tax_included: formValues.is_tax_included ?? false,
    is_active: true,
  })
})

onUnmounted(() => {
  revokePreview(primaryPreview.value)
  galleryImages.value.forEach((item) => {
    if (item.type === 'new') revokePreview(item.preview)
  })
})
</script>

<template>
  <section>
    <PageHeader
      :title="isEdit ? 'Edit Product' : 'Add New Product'"
      :subtitle="isEdit ? 'Update product details and images.' : 'Create a new product in the catalogue.'"
      :breadcrumb="[
        { label: 'Products', href: '/products' },
        { label: isEdit ? 'Edit' : 'New' },
      ]"
    />

    <div v-if="isEdit && productQuery.isLoading.value" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      Loading product…
    </div>

    <form v-else class="space-y-6" @submit.prevent="onSubmit">
      <div v-if="submitError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ submitError }}
      </div>

      <div class="grid gap-6 lg:grid-cols-[55%_45%]">
        <!-- Left panel -->
        <div class="space-y-4 rounded-lg border bg-white p-6 shadow-card">
          <h2 class="text-base font-semibold text-slate-900">Product Details</h2>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Title <span class="text-red-500">*</span></span>
            <Field name="title" class="w-full rounded-md border px-3 py-2" placeholder="Product title" />
            <ErrorMessage name="title" class="mt-1 block text-xs text-red-600" />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Model Number</span>
            <Field name="model_number" class="w-full rounded-md border px-3 py-2" placeholder="Optional model number" />
            <ErrorMessage name="model_number" class="mt-1 block text-xs text-red-600" />
            <p
              v-if="showModelWarning"
              class="mt-2 inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800"
            >
              Model already used by “{{ modelWarning?.title }}”
            </p>
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">
              Rate ({{ productCurrency?.code ?? 'RWF' }}) <span class="text-red-500">*</span>
            </span>
            <Field v-slot="{ field }" name="rate">
              <CurrencyInput
                :model-value="field.value"
                :currency="productCurrency"
                @update:model-value="field.onChange"
              />
            </Field>
            <ErrorMessage name="rate" class="mt-1 block text-xs text-red-600" />
            <label class="mt-3 flex items-start gap-2">
              <Field v-slot="{ field }" name="is_tax_included" type="checkbox" :value="true" :unchecked-value="false">
                <input
                  type="checkbox"
                  class="mt-0.5 rounded border-slate-300"
                  :checked="field.checked"
                  @change="field.onChange"
                />
              </Field>
              <span>
                <span class="font-medium text-slate-700">Price includes tax</span>
                <span class="mt-0.5 block text-xs text-slate-500">
                  When enabled, VAT is not added on top of this product in quotations.
                </span>
              </span>
            </label>
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Currency <span class="text-red-500">*</span></span>
            <Field as="select" name="currency_id" class="w-full rounded-md border px-3 py-2">
              <option value="">Select currency</option>
              <option v-for="currency in activeCurrencies" :key="currency.id" :value="currency.id">
                {{ currency.code }} — {{ currency.name }}
              </option>
            </Field>
            <ErrorMessage name="currency_id" class="mt-1 block text-xs text-red-600" />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Unit <span class="text-red-500">*</span></span>
            <Field as="select" name="unit_id" class="w-full rounded-md border px-3 py-2">
              <option value="">Select unit</option>
              <option v-for="unit in units" :key="unit.id" :value="unit.id">
                {{ unit.code }} — {{ unit.name }}
              </option>
            </Field>
            <ErrorMessage name="unit_id" class="mt-1 block text-xs text-red-600" />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Description</span>
            <Field
              as="textarea"
              name="description"
              rows="5"
              class="w-full rounded-md border px-3 py-2"
              placeholder="Optional product description"
            />
            <ErrorMessage name="description" class="mt-1 block text-xs text-red-600" />
          </label>
        </div>

        <!-- Right panel — Images -->
        <div class="space-y-4 rounded-lg border bg-white p-6 shadow-card">
          <h2 class="text-base font-semibold text-slate-900">Images</h2>

          <div>
            <p class="mb-2 text-sm font-medium text-slate-700">Primary Image</p>
            <div
              class="relative flex min-h-[200px] cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed transition-colors"
              :class="primaryDragOver ? 'border-brand-blue bg-brand-blue/5' : 'border-slate-300 bg-slate-50'"
              @dragover.prevent="primaryDragOver = true"
              @dragleave.prevent="primaryDragOver = false"
              @drop.prevent="onPrimaryDrop"
              @click="primaryInput?.click()"
            >
              <img
                v-if="primaryPreview"
                :src="primaryPreview"
                alt="Primary preview"
                class="max-h-48 w-full object-contain p-4"
                @click.stop
              />
              <div v-else class="flex flex-col items-center gap-2 p-6 text-slate-500">
                <Upload class="h-8 w-8" />
                <p class="text-sm">Click or drag to upload primary image</p>
              </div>
              <input ref="primaryInput" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="sr-only" @change="onPrimarySelect" />
            </div>
            <button
              v-if="primaryPreview"
              type="button"
              class="mt-2 text-sm text-red-600 hover:underline"
              @click="removePrimary"
            >
              Remove primary image
            </button>
          </div>

          <div>
            <div class="mb-2 flex items-center justify-between">
              <p class="text-sm font-medium text-slate-700">Gallery</p>
              <span class="text-xs text-slate-500">{{ galleryCount }}/{{ MAX_GALLERY }} images</span>
            </div>

            <div v-if="galleryCount" class="grid grid-cols-4 gap-2">
              <div
                v-for="element in galleryImages"
                :key="element.key"
                class="group relative aspect-square overflow-hidden rounded-md border bg-slate-100"
              >
                <img
                  :src="element.type === 'existing' ? element.image_url : element.preview"
                  alt="Gallery thumbnail"
                  class="h-full w-full object-cover"
                />
                <button
                  type="button"
                  class="absolute right-1 top-1 rounded-full bg-slate-950/70 p-1 text-white opacity-0 transition-opacity group-hover:opacity-100"
                  @click="removeGalleryItem(element)"
                >
                  <X class="h-3 w-3" />
                </button>
              </div>
            </div>

            <label
              v-if="galleryCount < MAX_GALLERY"
              class="mt-3 flex min-h-[80px] w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed transition-colors"
              :class="galleryDragOver ? 'border-brand-blue bg-brand-blue/5' : 'border-slate-300 bg-slate-50'"
              @dragover.prevent="galleryDragOver = true"
              @dragleave.prevent="galleryDragOver = false"
              @drop.prevent="onGalleryDrop"
            >
              <ImagePlus class="h-5 w-5 text-slate-400" />
              <p class="mt-1 text-xs text-slate-500">Add gallery images (click or drag)</p>
              <input
                ref="galleryInput"
                type="file"
                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                multiple
                class="sr-only"
                @change="onGallerySelect"
              />
            </label>
          </div>
        </div>
      </div>

      <div class="sticky bottom-0 z-10 -mx-4 border-t bg-white/95 px-4 py-4 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom))">
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button
            type="button"
            class="rounded-lg border px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            @click="router.push(isEdit && productId ? `/products/${productId}` : '/products')"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90 disabled:opacity-50"
            :disabled="saveMutation.isPending.value"
          >
            {{ saveMutation.isPending.value ? 'Saving…' : isEdit ? 'Update Product' : 'Create Product' }}
          </button>
        </div>
      </div>
    </form>
  </section>
</template>
