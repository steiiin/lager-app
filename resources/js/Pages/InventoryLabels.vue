<script setup>

/**
 * InventoryLabels - Page component
 *
 * This page enables the user to print labels..
 *
 */

// #region Imports

  // Vue composables
  import { computed, nextTick, reactive, ref } from 'vue'
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

  const selectedLabels = reactive({
    control: [],
    usage: [],
    item: [],
  })

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
        { key: 'name', label: 'Name' },
        { key: 'code', label: 'Code' },
        { key: 'demand', label: 'Anforderung' },
        { key: 'unit', label: 'Einheit' },
      ]
    }
    return [
      { key: 'name', label: 'Name' },
      { key: 'code', label: 'Code' },
    ]
  })

  const filter = reactive({
    query: '',
    column: 'all',
  })

  const filteredRows = computed(() => {
    if (!filter.query.trim()) { return currentRows.value }
    const query = filter.query.trim().toLowerCase()

    if (filter.column === 'all') {
      return currentRows.value.filter((row) => currentColumns.value.some((column) => `${row[column.key] ?? ''}`.toLowerCase().includes(query)))
    }

    return currentRows.value.filter((row) => `${row[filter.column] ?? ''}`.toLowerCase().includes(query))
  })

  const currentSelection = computed(() => selectedLabels[selectedTab.value] ?? [])

  const isAllSelected = computed(() => filteredRows.value.length > 0 && filteredRows.value.every((row) => currentSelection.value.includes(row.id)))

  const isSelectionIndeterminate = computed(() => currentSelection.value.length > 0 && !isAllSelected.value)

  const toggleSelectAll = (value) => {
    if (value) {
      selectedLabels[selectedTab.value] = filteredRows.value.map((row) => row.id)
    } else {
      selectedLabels[selectedTab.value] = []
    }
  }

  const formatCell = (row, key) => {
    const value = row[key]
    if (key === 'is_default') { return value ? 'Ja' : 'Nein' }
    return value
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

      <v-tabs
        v-model="selectedTab"
        color="primary"
      >
        <v-tab
          v-for="tab in tabOptions"
          :key="tab.value"
          :value="tab.value"
        >
          {{ tab.label }}
        </v-tab>
      </v-tabs>

      <v-window v-model="selectedTab">
        <v-window-item
          v-for="tab in tabOptions"
          :key="tab.value"
          :value="tab.value"
        >
          <v-card flat>

            <div class="d-flex flex-wrap ga-4 pa-4 align-center">
              <v-text-field
                v-model="filter.query"
                label="Filtern"
                prepend-inner-icon="mdi-magnify"
                hide-details
                density="compact"
                style="max-width: 320px"
              />
              <v-btn
                color="primary"
                variant="text"
                :prepend-icon="isAllSelected ? 'mdi-checkbox-marked' : 'mdi-checkbox-blank-outline'"
                @click="toggleSelectAll(!isAllSelected)"
              >
                {{ isAllSelected ? 'Alle abwählen' : 'Alle auswählen' }}
              </v-btn>
            </div>

            <v-table>
              <thead>
                <tr>
                  <th class="text-left">
                    <v-checkbox
                      class="ma-0"
                      color="primary"
                      hide-details
                      density="compact"
                      :indeterminate="isSelectionIndeterminate"
                      :model-value="isAllSelected"
                      @update:model-value="toggleSelectAll"
                    />
                  </th>
                  <th
                    v-for="column in currentColumns"
                    :key="column.key"
                    class="text-left"
                  >
                    {{ column.label }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in filteredRows" :key="row.id">
                  <td>
                    <v-checkbox
                      v-model="selectedLabels[tab.value]"
                      :value="row.id"
                      hide-details
                      density="compact"
                    />
                  </td>
                  <td
                    v-for="column in currentColumns"
                    :key="column.key"
                  >
                    {{ formatCell(row, column.key) }}
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>
      </v-window>

    </section>

  </div>

</template>
