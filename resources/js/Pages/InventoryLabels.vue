<script setup>

/**
 * InventoryLabels - Page component
 *
 * This page enables the user to print labels..
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, nextTick } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'

// #endregion
// #region Props

  defineProps({
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

    </section>

  </div>

</template>
