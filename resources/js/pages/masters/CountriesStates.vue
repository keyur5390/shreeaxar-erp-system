<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ChevronLeft, ChevronRight, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { countriesService } from '@/services/countries.service'
import { statesService } from '@/services/states.service'
import { useToast } from '@/composables/useToast'
import type { Country, State } from '@/types'
import { ValidationError } from '@/services/api'

const queryClient = useQueryClient()
const { toast } = useToast()

const search = ref('')
const page = ref(1)
const selectedCountry = ref<Country | null>(null)

const countryModalOpen = ref(false)
const editingCountry = ref<Country | null>(null)
const countryName = ref('')
const countryIso = ref('')
const countryFormError = ref('')
const deleteCountryTarget = ref<Country | null>(null)

const stateName = ref('')
const addingState = ref(false)
const stateFormError = ref('')
const deleteStateTarget = ref<State | null>(null)

const countriesQuery = useQuery({
  queryKey: computed(() => ['countries', page.value, search.value]),
  queryFn: () => countriesService.list({ page: page.value, per_page: 15, search: search.value || undefined }),
})

const statesQuery = useQuery({
  queryKey: computed(() => ['states', selectedCountry.value?.id]),
  queryFn: () => statesService.list(selectedCountry.value!.id),
  enabled: computed(() => Boolean(selectedCountry.value?.id)),
})

const countries = computed(() => countriesQuery.data.value?.items ?? [])
const pagination = computed(() => countriesQuery.data.value?.pagination)
const states = computed(() => statesQuery.data.value ?? [])

const saveCountryMutation = useMutation({
  mutationFn: async () => {
    const name = countryName.value.trim()
    const iso_code = countryIso.value.trim().toUpperCase()
    if (!name) throw new Error('Country name is required.')
    if (!/^[A-Z]{2,3}$/.test(iso_code)) throw new Error('ISO code must be 2–3 uppercase letters.')
    const payload = { name, iso_code }
    if (editingCountry.value) return countriesService.update(editingCountry.value.id, payload)
    return countriesService.create(payload)
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['countries'] })
    toast(editingCountry.value ? 'Country updated successfully.' : 'Country created successfully.', 'success')
    closeCountryModal()
  },
  onError: (error: unknown) => {
    countryFormError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to save country.'
  },
})

const deleteCountryMutation = useMutation({
  mutationFn: (country: Country) => countriesService.remove(country.id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['countries'] })
    if (selectedCountry.value?.id === deleteCountryTarget.value?.id) selectedCountry.value = null
    toast('Country deleted successfully.', 'success')
    deleteCountryTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete country.')
    toast(message, 'error')
    deleteCountryTarget.value = null
  },
})

const createStateMutation = useMutation({
  mutationFn: (name: string) => statesService.create({ name, country_id: selectedCountry.value!.id }),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['states', selectedCountry.value?.id] })
    queryClient.invalidateQueries({ queryKey: ['countries'] })
    toast('State created successfully.', 'success')
    stateName.value = ''
    addingState.value = false
    stateFormError.value = ''
  },
  onError: (error: unknown) => {
    stateFormError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to create state.'
  },
})

const deleteStateMutation = useMutation({
  mutationFn: (state: State) => statesService.remove(state.id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['states', selectedCountry.value?.id] })
    queryClient.invalidateQueries({ queryKey: ['countries'] })
    toast('State deleted successfully.', 'success')
    deleteStateTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete state.')
    toast(message, 'error')
    deleteStateTarget.value = null
  },
})

watch(search, () => { page.value = 1 })

function selectCountry(country: Country) {
  selectedCountry.value = country
  addingState.value = false
  stateName.value = ''
}

function openCreateCountry() {
  editingCountry.value = null
  countryName.value = ''
  countryIso.value = ''
  countryFormError.value = ''
  countryModalOpen.value = true
}

function openEditCountry(country: Country) {
  editingCountry.value = country
  countryName.value = country.name
  countryIso.value = country.iso_code
  countryFormError.value = ''
  countryModalOpen.value = true
}

function closeCountryModal() {
  countryModalOpen.value = false
  editingCountry.value = null
}

function onIsoInput(event: Event) {
  countryIso.value = (event.target as HTMLInputElement).value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3)
}

function saveState() {
  const name = stateName.value.trim()
  if (!name) {
    stateFormError.value = 'State name is required.'
    return
  }
  createStateMutation.mutate(name)
}
</script>

<template>
  <section>
    <PageHeader
      title="Countries & States"
      subtitle="Manage countries and their states or provinces."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Countries & States' }]"
    >
      <template #action>
        <PermissionGate module="countries" action="create">
          <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90" @click="openCreateCountry">
            <Plus class="h-4 w-4" />
            Add Country
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-2">
      <div class="rounded-lg border bg-white shadow-card">
        <div class="border-b p-4">
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input v-model="search" type="text" class="w-full rounded-md border py-2 pl-9 pr-3 text-sm" placeholder="Search by name or ISO code…" />
          </div>
        </div>

        <div v-if="countriesQuery.isLoading.value" class="p-6 text-sm text-slate-500">Loading countries…</div>
        <div v-else-if="!countries.length" class="p-6 text-sm text-slate-500">No countries found.</div>
        <ul v-else class="divide-y">
          <li
            v-for="country in countries"
            :key="country.id"
            class="flex cursor-pointer items-center justify-between px-4 py-3 hover:bg-slate-50"
            :class="{ 'bg-brand-accent/40': selectedCountry?.id === country.id }"
            @click="selectCountry(country)"
          >
            <div>
              <p class="font-medium text-slate-900">{{ country.name }}</p>
              <p class="text-xs text-slate-500">{{ country.iso_code }} · {{ country.states_count ?? 0 }} states</p>
            </div>
            <div class="flex items-center gap-1" @click.stop>
              <PermissionGate module="countries" action="edit">
                <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-200" @click="openEditCountry(country)"><Pencil class="h-4 w-4" /></button>
              </PermissionGate>
              <PermissionGate module="countries" action="delete">
                <button type="button" class="rounded p-1 text-slate-500 hover:bg-red-100 hover:text-red-700" @click="deleteCountryTarget = country"><Trash2 class="h-4 w-4" /></button>
              </PermissionGate>
            </div>
          </li>
        </ul>

        <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between border-t px-4 py-3 text-sm">
          <span class="text-slate-500">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <div class="flex gap-1">
            <button type="button" class="rounded border p-1 disabled:opacity-40" :disabled="page <= 1" @click="page--"><ChevronLeft class="h-4 w-4" /></button>
            <button type="button" class="rounded border p-1 disabled:opacity-40" :disabled="page >= pagination.last_page" @click="page++"><ChevronRight class="h-4 w-4" /></button>
          </div>
        </div>
      </div>

      <div class="rounded-lg border bg-white p-6 shadow-card">
        <template v-if="selectedCountry">
          <h2 class="text-lg font-semibold">{{ selectedCountry.name }} — States</h2>
          <p class="mt-1 text-sm text-slate-500">ISO {{ selectedCountry.iso_code }}</p>

          <div v-if="statesQuery.isLoading.value" class="mt-4 text-sm text-slate-500">Loading states…</div>
          <ul v-else-if="states.length" class="mt-4 space-y-2">
            <li v-for="state in states" :key="state.id" class="flex items-center justify-between rounded-md border px-3 py-2">
              <span class="text-sm font-medium">{{ state.name }}</span>
              <div class="flex items-center gap-2">
                <span v-if="state.addresses_count" class="text-xs text-slate-500">{{ state.addresses_count }} addresses</span>
                <PermissionGate module="countries" action="delete">
                  <button type="button" class="rounded p-1 text-slate-500 hover:bg-red-100 hover:text-red-700" @click="deleteStateTarget = state"><Trash2 class="h-3.5 w-3.5" /></button>
                </PermissionGate>
              </div>
            </li>
          </ul>
          <p v-else class="mt-4 text-sm text-slate-500">No states for this country.</p>

          <PermissionGate module="countries" action="create">
            <div v-if="addingState" class="mt-4 flex items-center gap-2">
              <input v-model="stateName" type="text" class="flex-1 rounded-md border px-3 py-2 text-sm" placeholder="State name" @keydown.enter.prevent="saveState" @keydown.esc.prevent="addingState = false" />
              <button type="button" class="rounded-md bg-brand-blue px-3 py-2 text-sm text-white" :disabled="createStateMutation.isPending.value" @click="saveState">Save</button>
              <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="addingState = false"><X class="h-4 w-4" /></button>
            </div>
            <button v-else type="button" class="mt-4 inline-flex items-center gap-1 text-sm text-brand-blue hover:underline" @click="addingState = true">
              <Plus class="h-4 w-4" /> Add State
            </button>
            <p v-if="stateFormError" class="mt-2 text-sm text-red-600">{{ stateFormError }}</p>
          </PermissionGate>
        </template>
        <p v-else class="text-sm text-slate-500">Select a country to manage its states.</p>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="countryModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
        <section class="w-full max-w-md rounded-lg bg-white p-6 shadow-card">
          <h2 class="text-lg font-semibold">{{ editingCountry ? 'Edit Country' : 'Add Country' }}</h2>
          <label class="mt-4 block text-sm font-medium">Name<input v-model="countryName" type="text" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
          <label class="mt-4 block text-sm font-medium">ISO Code<input :value="countryIso" type="text" class="mt-1 w-full rounded-md border px-3 py-2 font-mono uppercase" maxlength="3" @input="onIsoInput" /></label>
          <p v-if="countryFormError" class="mt-2 text-sm text-red-600">{{ countryFormError }}</p>
          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="rounded-md border px-4 py-2" @click="closeCountryModal">Cancel</button>
            <button type="button" class="rounded-md bg-brand-blue px-4 py-2 text-white disabled:opacity-50" :disabled="saveCountryMutation.isPending.value" @click="saveCountryMutation.mutate()">Save</button>
          </div>
        </section>
      </div>
    </Teleport>

    <ConfirmDialog
      :open="Boolean(deleteCountryTarget)"
      title="Delete country?"
      :description="deleteCountryTarget ? 'Delete ' + deleteCountryTarget.name + ' and all its states?' : ''"
      confirm-label="Delete"
      confirm-variant="destructive"
      @cancel="deleteCountryTarget = null"
      @confirm="deleteCountryTarget && deleteCountryMutation.mutate(deleteCountryTarget)"
    />

    <ConfirmDialog
      :open="Boolean(deleteStateTarget)"
      title="Delete state?"
      :description="deleteStateTarget ? 'Delete ' + deleteStateTarget.name + '?' : ''"
      confirm-label="Delete"
      confirm-variant="destructive"
      @cancel="deleteStateTarget = null"
      @confirm="deleteStateTarget && deleteStateMutation.mutate(deleteStateTarget)"
    />
  </section>
</template>
