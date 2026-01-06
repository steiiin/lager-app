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
    demands: {
      type: Array,
      required: true,
    },
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
    { value: 'control', label: 'Controllabels' },
    { value: 'usage', label: 'Usageslabels' },
    { value: 'item', label: 'Itemslabels' },
  ]
  const selectedTab = ref(tabOptions[0].value)

  const selectedLabels = reactive({
    control: [],
    usage: [],
    item: [],
  })

  const demandRows = computed(() => props.demands.map((demand, index) => ({
    id: demand.id ?? index,
    name: demand.name ?? '',
    code: demand.code ?? demand.id ?? index + 1,
  })))

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

    const isNewDemand = computed(() => editForm.id === null)

  // #endregion
  // #region EditDialog

    // DialogProps
    const editDialogVisible = ref(false)
    const editDialogTitle = computed(() => {
      return !editForm.id
        ? 'Neue Anforderung erstellen'
        : `${editForm.name} bearbeiten`
    })

    const isValidEdit = computed(() => editForm.name.trim().length>0)

    // OpenMethods
    const openNewDemandDialog = async () => {
      editForm.reset()
      editForm.clearErrors()
      editForm.id = null
      editForm.name = ''
      editDialogVisible.value = true
      await nextTick()
      document.getElementById('id-editdemand-name')?.focus()
    }
    const openEditDemandDialog = (item) => {
      editForm.reset()
      editForm.clearErrors()
      editForm.id = item.id
      editForm.name = item.name
      editDialogVisible.value = true
    }

    // ActionMethods
    const saveEdit = () => {
      if (!isValidEdit) { return }
      if (editForm.id === null) {
        editForm.post('/inventory-demands', editFormOptions)
      } else {
        editForm.put(`/inventory-demands/${editForm.id}`, editFormOptions)
      }
    }
    const cancelEdit = () => {
      editDialogVisible.value = false
    }
    const deleteDemand = () => {

      if (confirm('Willst du das wirklich löschen?')) {
        editForm.delete(`/inventory-demands/${editForm.id}`, editFormOptions)
      }

    }

  // #endregion

  // #region DemandTable

    const tableheaders = ref([
      { title: 'Name', key: 'name', minWidth: '100%' },
      { title: 'Bearbeiten', key: 'action', sortable: false },
    ])

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
            <v-table>
              <thead>
                <tr>
                  <th class="text-left">Wählen</th>
                  <th class="text-left">Name</th>
                  <th class="text-left">Code</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in demandRows" :key="row.id">
                  <td>
                    <v-checkbox
                      v-model="selectedLabels[tab.value]"
                      :value="row.id"
                      hide-details
                      density="compact"
                    />
                  </td>
                  <td>{{ row.name }}</td>
                  <td>{{ row.code }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>
      </v-window>

    </section>

  </div>

</template>
