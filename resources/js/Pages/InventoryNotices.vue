<script setup>

/**
 * InventoryNotices - Page component
 *
 * This page enables the user to create inventory notices.
 *
 */

// #region Imports

  // Vue composables
  import { computed, ref, watch, onMounted } from 'vue'
  import { Head, router } from '@inertiajs/vue3'

  // Local composables
  import { useInventoryStore } from '@/Services/StoreService'

  // Local components
  import LcFeedback from '@/Components/LcFeedback.vue'
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'

  // 3rd party components
  import axios from 'axios'

// #endregion
// #region Navigation

  // Router-Events
  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  // Routes
  function openInventory() {
    router.get('/inventory')
  }

// #endregion
// #region Store

  const inventoryStore = useInventoryStore()

  onMounted(() => {
    inventoryStore.fetchStore(true)
  })

// #endregion
// #region Tabs + Data

  const tabOptions = [
    { value: 'hygiene', label: 'Hygiene' },
    { value: 'onvehicle', label: 'OnVehicle' },
  ]
  const selectedTab = ref(tabOptions[0].value)

  const tableColumns = [
    { key: 'name', title: 'Name' },
    { key: 'symbol', title: 'Symbol' },
    { key: 'code', title: 'Code' },
  ]

  const symbolOptions = {
    hygiene: ['desinfektion', 'gloves', 'virus', 'chemical', 'housekeeper', 'trash'],
    onvehicle: ['meds', 'bandage', 'airway'],
  }

  const defaultSymbols = {
    hygiene: 'desinfektion',
    onvehicle: 'meds',
  }

  const symbolSelections = ref({
    hygiene: {},
    onvehicle: {},
  })

  const ensureSymbols = (items, tab, fallback) => {
    items.forEach((item) => {
      const id = item.code ?? item.id ?? item.name
      if (!id) { return }
      if (!symbolSelections.value[tab][id]) {
        symbolSelections.value[tab][id] = fallback
      }
    })
  }

  const updateSymbol = (tab, rowId, value, fallback) => {
    symbolSelections.value[tab][rowId] = value || fallback
  }

  const inventoryItems = computed(() => inventoryStore.items ?? [])
  const hygieneItems = computed(() => inventoryItems.value.filter((item) => item.demand?.name === 'Hygiene'))
  const onvehicleItems = computed(() => inventoryItems.value.filter((item) => Number(item.max_stock) === 0))

  watch(hygieneItems, (items) => ensureSymbols(items, 'hygiene', defaultSymbols.hygiene), { immediate: true })
  watch(onvehicleItems, (items) => ensureSymbols(items, 'onvehicle', defaultSymbols.onvehicle), { immediate: true })

  const mapRow = (item, tab) => {
    const id = item.code ?? item.id ?? item.name
    return {
      id,
      name: item.name ?? '',
      code: item.code ?? '',
      symbol: symbolSelections.value[tab][id] ?? defaultSymbols[tab],
    }
  }

  const hygieneRows = computed(() => hygieneItems.value.map((item) => mapRow(item, 'hygiene')))
  const onvehicleRows = computed(() => onvehicleItems.value.map((item) => mapRow(item, 'onvehicle')))

  const filter = ref('')

  const rowsForTab = (tab) => {
    const rows = tab === 'hygiene' ? hygieneRows.value : onvehicleRows.value
    if (!filter.value.trim()) { return rows }
    const query = filter.value.trim().toLowerCase()
    return rows.filter((row) => tableColumns.some((column) => `${row[column.key] ?? ''}`.toLowerCase().includes(query)))
  }

// #endregion
// #region Actions

  const processing = ref(false)
  const feedback = ref(null)

  const submitNotice = async (tab) => {
    processing.value = true

    try
    {
      const payload = {
        items: rowsForTab(tab).map(({ id, ...row }) => row),
      }

      await axios.post(`/inventory-notices/${tab}`, payload)
      feedback.value?.success('Aushang erstellt', 'Der Aushang wurde gesendet.')
    }
    catch (error)
    {
      feedback.value?.error('Fehler', 'Der Aushang konnte nicht erstellt werden.')
    }
    finally
    {
      processing.value = false
    }
  }

// #endregion

</script>

<template>

  <Head title="InventoryNotices" />

  <div class="page-inventorynotices">

    <LcPagebar title="InventoryNotices" @back="openInventory" />

    <section>

      <div class="d-flex flex-wrap ga-4 py-4 align-center">

        <v-text-field v-model="filter" label="Filtern"
          prepend-inner-icon="mdi-magnify" hide-details density="compact"
          style="max-width: 320px">
        </v-text-field>

      </div>

      <v-tabs v-model="selectedTab"
        color="black" align-tabs="center" fixed-tabs>

        <v-tab v-for="tab in tabOptions" :key="tab.value" :value="tab.value">
          {{ tab.label }}
        </v-tab>

      </v-tabs>

      <v-window v-model="selectedTab">
        <v-window-item v-for="tab in tabOptions" :key="tab.value" :value="tab.value">

          <v-card flat>

            <div class="d-flex justify-end py-2">
              <v-btn color="primary" variant="text" prepend-icon="mdi-file-document-outline"
                :loading="processing"
                @click="submitNotice(tab.value)">Aushang erstellen
              </v-btn>
            </div>

            <v-data-table-virtual
              v-if="selectedTab === tab.value"
              :headers="tableColumns"
              :items="rowsForTab(tab.value)"
              item-value="id"
              fixed-header
              height="560"
              density="compact"
              :items-per-page="1000"
              hide-default-footer>

              <template #item.symbol="{ item }">
                <v-combobox
                  :items="symbolOptions[tab.value]"
                  density="compact"
                  hide-details
                  :model-value="symbolSelections[tab.value][item.id] ?? defaultSymbols[tab.value]"
                  @update:model-value="(value) => updateSymbol(tab.value, item.id, value, defaultSymbols[tab.value])">
                </v-combobox>
              </template>
            </v-data-table-virtual>

          </v-card>
        </v-window-item>
      </v-window>

    </section>

  </div>

  <LcFeedback ref="feedback"></LcFeedback>
  <LcRouteOverlay v-if="processing || isRouting"></LcRouteOverlay>

</template>
