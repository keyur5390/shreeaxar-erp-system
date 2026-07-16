<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ChevronLeft, ChevronRight, Copy, Pencil, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import { productsService } from '@/services/products.service'
import { useToast } from '@/composables/useToast'
import { formatCurrency, formatDate } from '@/utils/formatters'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()

const productId = computed(() => String(route.params.id))
const deleteOpen = ref(false)
const optimisticActive = ref<boolean | null>(null)
const selectedImageIndex = ref(0)
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const lightboxRef = ref<HTMLElement | null>(null)

const productQuery = useQuery({
  queryKey: computed(() => ['products', productId.value]),
  queryFn: () => productsService.get(productId.value),
})

const product = computed(() => productQuery.data.value)
const isActive = computed(() => optimisticActive.value ?? product.value?.is_active ?? false)

const allImages = computed(() => {
  const images: string[] = []
  if (product.value?.primary_image_url) images.push(product.value.primary_image_url)
  product.value?.images.forEach((image) => {
    if (image.image_url && !images.includes(image.image_url)) {
      images.push(image.image_url)
    }
  })
  return images
})

const mainImage = computed(() => allImages.value[selectedImageIndex.value] ?? null)
const lightboxImage = computed(() => allImages.value[lightboxIndex.value] ?? null)

watch(allImages, (images) => {
  if (selectedImageIndex.value >= images.length) selectedImageIndex.value = 0
})

function selectImage(index: number) {
  selectedImageIndex.value = index
}

function openLightbox(index = selectedImageIndex.value) {
  lightboxIndex.value = index
  lightboxOpen.value = true
  nextTick(() => lightboxRef.value?.focus())
}

function closeLightbox() {
  lightboxOpen.value = false
}

function cycleLightbox(direction: 1 | -1) {
  if (!allImages.value.length) return
  lightboxIndex.value = (lightboxIndex.value + direction + allImages.value.length) % allImages.value.length
}

function onLightboxKeydown(event: KeyboardEvent) {
  if (!lightboxOpen.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    closeLightbox()
  } else if (event.key === 'ArrowRight') {
    event.preventDefault()
    cycleLightbox(1)
  } else if (event.key === 'ArrowLeft') {
    event.preventDefault()
    cycleLightbox(-1)
  }
}

onMounted(() => window.addEventListener('keydown', onLightboxKeydown))
onUnmounted(() => window.removeEventListener('keydown', onLightboxKeydown))

const deleteDescription = computed(() => {
  const count = product.value?.usage_stats?.quotations_count ?? 0
  if (count > 0) {
    return `This product is used in ${count} quotation${count === 1 ? '' : 's'} and will be deactivated, not permanently deleted.`
  }
  return `Permanently delete ${product.value?.title}? This action cannot be undone.`
})

const toggleMutation = useMutation({
  mutationFn: () => productsService.toggleStatus(productId.value),
  onMutate: () => {
    optimisticActive.value = !isActive.value
  },
  onSuccess: (data) => {
    optimisticActive.value = data.is_active
    queryClient.invalidateQueries({ queryKey: ['products'] })
    queryClient.invalidateQueries({ queryKey: ['products', productId.value] })
    toast(data.is_active ? 'Product activated successfully.' : 'Product deactivated successfully.', 'success')
  },
  onError: (error: unknown) => {
    optimisticActive.value = null
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to update status.')
    toast(message, 'error')
  },
})

const deleteMutation = useMutation({
  mutationFn: () => productsService.remove(productId.value),
  onSuccess: (result) => {
    queryClient.invalidateQueries({ queryKey: ['products'] })
    toast(result.message ?? (result.soft_deleted ? 'Product deactivated successfully.' : 'Product deleted successfully.'), 'success')
    router.push('/products')
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete product.')
    toast(message, 'error')
    deleteOpen.value = false
  },
})

const duplicateMutation = useMutation({
  mutationFn: () => productsService.duplicate(productId.value),
  onSuccess: (result) => {
    queryClient.invalidateQueries({ queryKey: ['products'] })
    if (result.warnings.length) {
      toast(`Product duplicated with ${result.warnings.length} image warning(s).`, 'info')
    } else {
      toast('Product duplicated successfully.', 'success')
    }
    router.push(`/products/${result.product.id}/edit`)
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to duplicate product.')
    toast(message, 'error')
  },
})
</script>

<template>
  <section>
    <PageHeader
      :title="product?.title || 'Product Detail'"
      subtitle="View product information, images, and usage history."
      :breadcrumb="[
        { label: 'Products', href: '/products' },
        { label: product?.title || 'Detail' },
      ]"
    >
      <template #action>
        <div class="flex flex-wrap items-center gap-2">
          <StatusBadge
            v-if="product"
            :label="isActive ? 'Active' : 'Inactive'"
            :color="isActive ? '#16a34a' : '#94a3b8'"
          />
          <PermissionGate module="products" action="edit">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm hover:bg-slate-50"
              @click="router.push(`/products/${productId}/edit`)"
            >
              <Pencil class="h-4 w-4" />
              Edit
            </button>
            <ToggleSwitch
              :model-value="isActive"
              :disabled="toggleMutation.isPending.value"
              label="Toggle product status"
              @update:model-value="toggleMutation.mutate()"
            />
          </PermissionGate>
          <PermissionGate module="products" action="create">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm hover:bg-slate-50"
              :disabled="duplicateMutation.isPending.value"
              @click="duplicateMutation.mutate()"
            >
              <Copy class="h-4 w-4" />
              Duplicate Product
            </button>
          </PermissionGate>
          <PermissionGate module="products" action="delete">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50"
              @click="deleteOpen = true"
            >
              <Trash2 class="h-4 w-4" />
              Delete
            </button>
          </PermissionGate>
        </div>
      </template>
    </PageHeader>

    <div v-if="productQuery.isLoading.value" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      Loading product…
    </div>

    <template v-else-if="product">
      <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <!-- Image gallery -->
        <div class="rounded-lg border bg-white p-6 shadow-card">
          <div
            class="mb-4 flex aspect-square cursor-zoom-in items-center justify-center overflow-hidden rounded-lg bg-slate-100"
            @click="mainImage && openLightbox(selectedImageIndex)"
          >
            <img
              v-if="mainImage"
              :src="mainImage"
              :alt="product.title"
              class="h-full w-full object-contain"
            />
            <p v-else class="text-sm text-slate-400">No images</p>
          </div>
          <div v-if="allImages.length > 1" class="flex gap-2 overflow-x-auto pb-1">
            <button
              v-for="(image, index) in allImages"
              :key="`${image}-${index}`"
              type="button"
              class="h-16 w-16 shrink-0 overflow-hidden rounded-md border-2 transition-colors"
              :class="selectedImageIndex === index ? 'border-brand-blue' : 'border-transparent'"
              @click="selectImage(index)"
            >
              <img :src="image" :alt="`${product.title} ${index + 1}`" class="h-full w-full object-cover" />
            </button>
          </div>
        </div>

        <!-- Product info -->
        <div class="space-y-6">
          <div class="rounded-lg border bg-white p-6 shadow-card">
            <h2 class="mb-4 text-base font-semibold text-slate-900">Product Information</h2>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
              <div class="sm:col-span-2">
                <dt class="text-slate-500">Title</dt>
                <dd class="text-lg font-semibold text-slate-900">{{ product.title }}</dd>
              </div>
              <div>
                <dt class="text-slate-500">Model Number</dt>
                <dd class="font-medium text-slate-900">{{ product.model_number || '—' }}</dd>
              </div>
              <div>
                <dt class="text-slate-500">Unit</dt>
                <dd class="font-medium text-slate-900">
                  {{ product.unit ? `${product.unit.code} — ${product.unit.name}` : '—' }}
                </dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-slate-500">Rate</dt>
                <dd class="text-2xl font-bold text-brand-blue">{{ formatCurrency(product.rate) }}</dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-slate-500">Description</dt>
                <dd class="whitespace-pre-wrap text-slate-700">{{ product.description || '—' }}</dd>
              </div>
            </dl>
          </div>

          <div v-if="product.usage_stats" class="rounded-lg border bg-white p-6 shadow-card">
            <h2 class="mb-4 text-base font-semibold text-slate-900">Usage Statistics</h2>
            <dl class="grid gap-4 text-sm sm:grid-cols-3">
              <div>
                <dt class="text-slate-500">Used in quotations</dt>
                <dd class="text-xl font-semibold text-slate-900">{{ product.usage_stats.quotations_count }}</dd>
              </div>
              <div>
                <dt class="text-slate-500">Units sold</dt>
                <dd class="text-xl font-semibold text-slate-900">{{ product.usage_stats.total_qty_sold }}</dd>
                <p class="text-xs text-slate-400">Approved / Accepted only</p>
              </div>
              <div>
                <dt class="text-slate-500">Last used</dt>
                <dd class="text-xl font-semibold text-slate-900">
                  {{ product.usage_stats.last_used_date ? formatDate(product.usage_stats.last_used_date) : '—' }}
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </div>

      <!-- Recent quotation items -->
      <div class="rounded-lg border bg-white p-6 shadow-card">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Recent Quotation Items</h2>
        <div v-if="!product.recent_quotation_items?.length" class="text-sm text-slate-500">
          This product has not appeared in any quotations yet.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-slate-500">Quotation</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-500">Customer</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-500">Date</th>
                <th class="px-4 py-3 text-right font-semibold text-slate-500">Qty</th>
                <th class="px-4 py-3 text-right font-semibold text-slate-500">Line Total</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-500">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in product.recent_quotation_items" :key="item.id">
                <td class="px-4 py-3 font-medium text-slate-900">{{ item.quotation_number || '—' }}</td>
                <td class="px-4 py-3 text-slate-700">{{ item.customer_name || '—' }}</td>
                <td class="px-4 py-3 text-slate-700">
                  {{ item.quotation_date ? formatDate(item.quotation_date) : '—' }}
                </td>
                <td class="px-4 py-3 text-right text-slate-700">{{ item.quantity }}</td>
                <td class="px-4 py-3 text-right font-medium text-slate-900">{{ formatCurrency(item.line_total) }}</td>
                <td class="px-4 py-3">
                  <StatusBadge
                    v-if="item.status"
                    :label="item.status.name"
                    :color="item.status.color"
                    size="sm"
                  />
                  <span v-else>—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Lightbox -->
    <Teleport to="body">
      <div
        v-if="lightboxOpen"
        ref="lightboxRef"
        tabindex="-1"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 p-4 outline-none"
        role="dialog"
        aria-modal="true"
        aria-label="Image lightbox"
        @click.self="closeLightbox"
      >
        <button
          type="button"
          class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
          aria-label="Close lightbox"
          @click="closeLightbox"
        >
          <X class="h-6 w-6" />
        </button>

        <button
          v-if="allImages.length > 1"
          type="button"
          class="absolute left-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
          aria-label="Previous image"
          @click.stop="cycleLightbox(-1)"
        >
          <ChevronLeft class="h-6 w-6" />
        </button>

        <img
          v-if="lightboxImage"
          :src="lightboxImage"
          :alt="product?.title"
          class="max-h-[90vh] max-w-[90vw] object-contain"
          @click.stop
        />

        <button
          v-if="allImages.length > 1"
          type="button"
          class="absolute right-16 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
          aria-label="Next image"
          @click.stop="cycleLightbox(1)"
        >
          <ChevronRight class="h-6 w-6" />
        </button>
      </div>
    </Teleport>

    <ConfirmDialog
      :open="deleteOpen"
      title="Delete product?"
      :description="deleteDescription"
      confirm-label="Delete"
      confirm-variant="destructive"
      @cancel="deleteOpen = false"
      @confirm="deleteMutation.mutate()"
    />
  </section>
</template>
