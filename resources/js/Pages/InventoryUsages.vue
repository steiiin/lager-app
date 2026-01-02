<script setup>

/**
 * InventoryUsages - Page component
 *
 * This page enables the user to Inventory the app-wide usages.
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
    usages: {
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

// #region Usages-Logic

  // #region EditForm

    const editForm = useForm({
      id: null,
      name: '',
    })
    const editFormOptions = {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ['usages'] })
        editDialogVisible.value = false
      },
    }

    const isNewUsage = computed(() => editForm.id === null)

  // #endregion
  // #region EditDialog

    // DialogProps
    const editDialogVisible = ref(false)
    const editDialogTitle = computed(() => {
      return !editForm.id
        ? 'Neue Verwendung erstellen'
        : `${editForm.name} bearbeiten`
    })

    const isValidEdit = computed(() => editForm.name.trim().length > 0)

    // OpenMethods
    const openNewUsageDialog = async () => {
      editForm.reset()
      editForm.clearErrors()
      editForm.id = null
      editForm.name = ''
      editDialogVisible.value = true
      await nextTick()
      document.getElementById('id-editusage-name')?.focus()
    }
    const openEditUsageDialog = (item) => {
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
        editForm.post('/inventory-usages', editFormOptions)
      } else {
        editForm.put(`/inventory-usages/${editForm.id}`, editFormOptions)
      }
    }
    const cancelEdit = () => {
      editDialogVisible.value = false
    }
    const deleteUsage = () => {

      if (confirm('Do you really want to delete this?')) {
        editForm.delete(`/inventory-usages/${editForm.id}`, editFormOptions)
      }

    }

  // #endregion

  // #region UsageTable

    const tableheaders = ref([
      { title: 'Name', key: 'name', minWidth: '100%' },
      { title: 'Bearbeiten', key: 'action', sortable: false },
    ])

  // #endregion

// #endregion

</script>

<template>

  <Head title="Verwendungen" />

  <div class="page-inventoryusages">

    <LcPagebar title="Verwendungen" @back="openInventory" />

    <section>

      <v-toolbar>
        <v-spacer></v-spacer>
        <v-toolbar-items>
          <v-btn prepend-icon="mdi-plus"
            @click="openNewUsageDialog()">Hinzufügen
          </v-btn>
        </v-toolbar-items>
      </v-toolbar>

      <v-data-table
        :items="usages" :headers="tableheaders"
        hide-default-footer :items-per-page="100">
        <template v-slot:item.action="{ item }">
          <v-btn small color="primary" @click="openEditUsageDialog(item)">
            <v-icon icon="mdi-cog"></v-icon>
          </v-btn>
        </template>
      </v-data-table>

    </section>

  </div>

  <!-- EditDialog -->
  <v-dialog v-model="editDialogVisible" max-width="450px">
    <v-card prepend-icon="mdi-truck" :title="editDialogTitle" :disabled="editForm.processing" class="rounded-0">

      <v-divider></v-divider>

      <v-card-text>
        <p class="mb-4">
          Eine Verwendung muss beim Buchen angegeben werden.
        </p>
        <v-text-field
          v-model="editForm.name" id="id-editusage-name"
          label="Name" hide-details>
        </v-text-field>

        <template v-if="editForm.errors">

          <v-alert v-for="(errorMessage, fieldName) in editForm.errors" type="error" :key="fieldName" class="mt-4">
            {{ errorMessage }}
          </v-alert>

        </template>

      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="mx-4 mb-2">
        <v-btn color="error"
          variant="tonal" v-if="!isNewUsage"
          @click="deleteUsage">Löschen</v-btn>
        <v-spacer></v-spacer>
        <v-btn @click="cancelEdit">Abbrechen</v-btn>
        <v-btn color="primary" :loading="editForm.processing"
          variant="tonal" :disabled="!isValidEdit"
          @click="saveEdit">Speichern</v-btn>
      </v-card-actions>

    </v-card>
  </v-dialog>

</template>
