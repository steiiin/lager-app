<script setup>

/**
 * ConfigDemands - Page component
 *
 * This page enables the user to config the app-wide demands.
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
      editForm.id = null
      editForm.name = ''
      editDialogVisible.value = true
      await nextTick()
      document.getElementById('id-editdemand-name')?.focus()
    }
    const openEditDemandDialog = (item) => {
      editForm.reset()
      editForm.id = item.id
      editForm.name = item.name
      editDialogVisible.value = true
    }

    // ActionMethods
    const saveEdit = () => {
      if (!isValidEdit) { return }
      if (editForm.id === null) {
        editForm.post('/config-demands', editFormOptions)
      } else {
        editForm.put(`/config-demands/${editForm.id}`, editFormOptions)
      }
    }
    const cancelEdit = () => {
      editDialogVisible.value = false
    }
    const deleteDemand = () => {

      if (confirm('Do you really want to delete this?')) {
        editForm.delete(`/config-demands/${editForm.id}`, editFormOptions)
      }

    }

  // #endregion

  // #region DemandTable

    const tableheaders = ref([
      { title: 'Name', key: 'name', minWidth: '90%' },
      { title: 'Bearbeiten', key: 'action', sortable: false },
    ])

  // #endregion

// #endregion

</script>

<template>

  <Head title="Anforderungen" />

  <div class="page-configdemands">

    <LcPagebar title="Anforderungen" @back="openInventory" />

    <section>

      <v-toolbar>
        <v-spacer></v-spacer>
        <v-toolbar-items>
          <v-btn prepend-icon="mdi-plus"
            @click="openNewDemandDialog()">Hinzufügen
          </v-btn>
        </v-toolbar-items>
      </v-toolbar>

      <v-data-table
        :items="demands" :headers="tableheaders"
        hide-default-footer :items-per-page="100">
        <template v-slot:item.sharepoint_use="{ item }">
          <span>{{ item.sharepoint_use ? 'Ja' : 'Nein' }}</span>
        </template>
        <template v-slot:item.mail_use="{ item }">
          <span>{{ item.mail_use ? 'Ja' : 'Nein' }}</span>
        </template>
        <template v-slot:item.action="{ item }">
          <v-btn small color="primary"
            @click="openEditDemandDialog(item)">
            <v-icon icon="mdi-cog"></v-icon>
          </v-btn>
        </template>
      </v-data-table>

    </section>

  </div>

  <!-- EditDialog -->
  <v-dialog v-model="editDialogVisible" max-width="750px">
    <v-card prepend-icon="mdi-file" :title="editDialogTitle" :disabled="editForm.processing" class="rounded-0">

      <v-divider></v-divider>

      <v-card-text>
        <p class="mb-4">
          Ein Artikel wird einer bestimmten Anforderung zugeordnet, damit die Bestellung im richtigen Fachbereich ankommt. <br>
          Du kannst auch festlegen, ob eine Bestellung im Sharepoint hinterlegt wird, oder per E-Mail gesendet werden soll.
        </p>
        <v-text-field v-model="editForm.name"
          id="id-editdemand-name"
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
        <v-btn v-if="!isNewDemand"
          color="error" variant="tonal"
          @click="deleteDemand">Löschen</v-btn>
        <v-spacer></v-spacer>
        <v-btn
          @click="cancelEdit">Abbrechen
        </v-btn>
        <v-btn color="primary" variant="tonal"
          :loading="editForm.processing" :disabled="!isValidEdit"
          @click="saveEdit">Speichern
        </v-btn>
      </v-card-actions>

    </v-card>
  </v-dialog>

</template>
