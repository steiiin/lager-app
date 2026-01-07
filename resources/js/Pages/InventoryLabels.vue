<script setup>

/**
 * InventoryLabels - Page component
 *
 * This page enables the user to print labels..
 *
 */

// #region Imports

  // Vue composables
  import { computed, nextTick, reactive, ref, watch } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'

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
    { value: 'control', label: 'Ctrl' },
    { value: 'usage', label: 'Verwendungen' },
    { value: 'item', label: 'Artikel' },
  ]
  const selectedTab = ref(tabOptions[0].value)

  const ctrlRows = computed(() => props.ctrl.map((ctrl) => ({
    id: ctrl.code ?? ctrl.name,
    name: ctrl.name ?? '',
    code: ctrl.code ?? '',
  })))

  const usagesRows = computed(() => props.usages.map((usage) => ({
    id: usage.code ?? usage.name,
    name: usage.name ?? '',
    code: usage.code ?? '',
  })))

  const itemsRows = computed(() => props.items.map((item) => ({
    id: item.code ?? item.name,
    name: item.name ?? '',
    code: item.code ?? '',
    demand: item.demand ?? '',
    unit: item.unit ?? '',
  })))

  const currentRows = computed(() => {
    if (selectedTab.value === 'control') { return ctrlRows.value }
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
      { key: 'code', title: 'Code' },
    ]
  })

  const filter = ref('')

  const filteredRows = computed(() => {
    if (!filter.value.trim()) { return currentRows.value }
    const query = filter.value.trim().toLowerCase()

    return currentRows.value.filter((row) => currentColumns.value.some((column) => `${row[column.key] ?? ''}`.toLowerCase().includes(query)))
  })

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

  const printForm = useForm({
    ctrl: [],
    usage: [],
    item: [],
  })

  const printLabels = () => {
    debugger
  }

// #endregion

// #region Demand-Logic

  // #region EditForm

    const editForm = useForm({
      id: null,
      name: '',
    })
    const editFormOptions = {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ['demands'] })
        editDialogVisible.value = false
      },
    }

  // #endregion

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
              v-model:selected="selectionModel"
              :headers="currentColumns"
              :items="filteredRows"
              item-value="id"
              show-select
              fixed-header
              height="560"
              density="compact"
              hide-default-footer>
            </v-data-table-virtual>

          </v-card>
        </v-window-item>
      </v-window>

    </section>

  </div>

</template>
