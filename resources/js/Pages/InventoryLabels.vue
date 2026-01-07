<script setup>

/**
 * InventoryLabels - Page component
 *
 * This page enables the user to print labels..
 *
 */

// #region Imports

  // Vue composables
  import { computed, ref, watch } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'
  import axios from 'axios'

// #endregion
// #region Props

  const props = defineProps({
    ctrl: {
      type: Array,
      required: true,
    },
    usages: {
      type: Array,
      required: true,
    },
    items: {
      type: Array,
      required: true,
    }
  })

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

// #region Tabs

  const tabOptions = [
    { value: 'ctrl', label: 'Ctrl' },
    { value: 'usage', label: 'Verwendungen' },
    { value: 'item', label: 'Artikel' },
  ]
  const selectedTab = ref(tabOptions[0].value)

  const symbolOptions = ['check', 'home', 'expiry', 'truck']

  const symbolSelections = ref({})

  const normalizeSymbol = (symbol, fallback) => {
    const mapped = {
      'check-bold': 'check',
      'home-city-outline': 'home',
      'timer-alert-outline': 'expiry',
      'truck-outline': 'truck',
    }

    if (!symbol) { return fallback }

    return mapped[symbol] ?? symbol
  }

  const hydrateSymbols = (rows, fallback) => {
    rows.forEach((row) => {
      const id = row.code ?? row.name
      if (!id) { return }
      if (!symbolSelections.value[id]) {
        symbolSelections.value[id] = normalizeSymbol(row.symbol, fallback)
      }
    })
  }

  const updateSymbol = (rowId, value, fallback) => {
    symbolSelections.value[rowId] = value || fallback
  }

  const ctrlRows = computed(() => props.ctrl.map((ctrl) => ({
    id: ctrl.code ?? ctrl.name,
    name: ctrl.name ?? '',
    code: ctrl.code ?? '',
    symbol: symbolSelections.value[ctrl.code ?? ctrl.name] ?? normalizeSymbol(ctrl.symbol, 'check'),
  })))

  const usagesRows = computed(() => props.usages.map((usage) => ({
    id: usage.code ?? usage.name,
    name: usage.name ?? '',
    code: usage.code ?? '',
    symbol: symbolSelections.value[usage.code ?? usage.name] ?? normalizeSymbol(usage.symbol, 'truck'),
  })))

  const itemsRows = computed(() => props.items.map((item) => ({
    id: item.code ?? item.name,
    name: item.name ?? '',
    code: item.code ?? '',
    demand: item.demand ?? '',
    unit: item.unit ?? '',
  })))

  const currentRows = computed(() => {
    if (selectedTab.value === 'ctrl') { return ctrlRows.value }
    if (selectedTab.value === 'usage') { return usagesRows.value }
    return itemsRows.value
  })

  const currentColumns = computed(() => {
    if (selectedTab.value === 'item') {
      return [
        { key: 'name', title: 'Name' },
        { key: 'code', title: 'Code' },
        { key: 'demand', title: 'Anforderung' },
        { key: 'unit', title: 'Einheit' },
      ]
    }
    return [
      { key: 'name', title: 'Name' },
      { key: 'symbol', title: 'Icon' },
      { key: 'code', title: 'Code' },
    ]
  })

  const filter = ref('')

  const filteredRows = computed(() => {
    if (!filter.value.trim()) { return currentRows.value }
    const query = filter.value.trim().toLowerCase()

    return currentRows.value.filter((row) => currentColumns.value.some((column) => `${row[column.key] ?? ''}`.toLowerCase().includes(query)))
  })

  const printForm = useForm({
    ctrl: [],
    usage: [],
    item: [],
  })

  watch(
    () => props.ctrl,
    (rows) => hydrateSymbols(rows, 'check'),
    { immediate: true }
  )

  watch(
    () => props.usages,
    (rows) => hydrateSymbols(rows, 'truck'),
    { immediate: true }
  )

  const selectionModel = computed({
    get: () => {
      if (selectedTab.value === 'ctrl') { return printForm.ctrl ?? [] }
      if (selectedTab.value === 'usage') { return printForm.usage ?? [] }
      if (selectedTab.value === 'item') { return printForm.item ?? [] }

      return []
    },
    set: (value) => {
      if (selectedTab.value === 'ctrl') { printForm.ctrl = value }
      if (selectedTab.value === 'usage') { printForm.usage = value }
      if (selectedTab.value === 'item') { printForm.item = value }
    },
  })

// #endregion

// #region Print

  const processing = ref(false)

  const printLabels = async () => {

    processing.value = true

    try
    {

      const mapSelection = (selection, fallback) => selection.map((code) => ({
        code,
        symbol: symbolSelections.value[code] ?? fallback,
      }))

      const response = await axios.post(
        route('inventory-labels.store'),
        {
          ctrl: mapSelection(printForm.ctrl, 'check'),
          usage: mapSelection(printForm.usage, 'truck'),
          item: printForm.item,
        },
        {
          responseType: 'blob',
        }
      )

      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', 'labels.pdf')
      document.body.appendChild(link)
      link.click()

    }
    finally
    {
      processing.value = false
    }

  }

// #endregion

</script>

<template>

  <Head title="Labels" />

  <div class="page-inventorylabels">

    <LcPagebar title="Labels" @back="openInventory" />

    <section>

      <div class="d-flex flex-wrap ga-4 py-4 align-center">

        <v-text-field v-model="filter" label="Filtern"
          prepend-inner-icon="mdi-magnify" hide-details density="compact"
          style="max-width: 320px">
        </v-text-field>

        <v-btn color="primary" variant="text" prepend-icon="mdi-printer"
          @click="printLabels">Drucken
        </v-btn>

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

            <v-data-table-virtual
              v-if="selectedTab === tab.value"
              v-model="selectionModel"
              :headers="currentColumns"
              :items="filteredRows"
              item-value="id"
              show-select
              fixed-header
              height="560"
              density="compact"
              :items-per-page="1000"
              hide-default-footer>

              <template v-if="selectedTab !== 'item'" #item.symbol="{ item }">
                <v-combobox
                  :items="symbolOptions"
                  density="compact"
                  hide-details
                  :model-value="symbolSelections[item.code]"
                  @update:model-value="(value) => updateSymbol(item.code, value, selectedTab === 'ctrl' ? 'check' : 'truck')">
                </v-combobox>
              </template>
            </v-data-table-virtual>

          </v-card>
        </v-window-item>
      </v-window>

    </section>

  </div>

  <LcRouteOverlay v-if="processing"></LcRouteOverlay>

</template>
