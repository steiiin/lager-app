<script setup>

// #region imports

  // Vue composables
  import { ref, computed, nextTick } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcConfirm from '@/Dialogs/LcConfirm.vue'

// #endregion

// #region props

  defineProps({
    usages: {
      type: Array,
      required: true,
    },
  })

// #endregion

// #region navigation

  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  function openInventory() {
    router.get('/inventory')
  }

// #endregion

// #region usages

  // #region form-data

    const editForm = useForm({
      id: null,
      name: '',
      is_locked: false,
    })
    const editFormOptions = {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ['usages'] })
        isEditDialogVisible.value = false
      },
    }

  // #endregion

  // #region edit-dialog

    // props
    const isEditDialogVisible = ref(false)
    const editDialogTitle = computed(() => {
      return !editForm.id
        ? 'Neue Verwendung erstellen'
        : `${editForm.name} bearbeiten`
    })
    const isValidEdit = computed(() => {
      return editForm.name.trim().length > 0
    })

    // open-dialog
    const openNewUsageDialog = async () => {
      editForm.reset()
      editForm.id = null
      editForm.name = ''
      editForm.is_locked = false
      isEditDialogVisible.value = true
      await nextTick()
      document.getElementById('id-editusage-name')?.focus()
    }
    const openEditUsageDialog = (item) => {
      editForm.reset()
      editForm.id = item.id
      editForm.name = item.name
      editForm.is_locked = item.is_locked ? true : false
      isEditDialogVisible.value = true
    }

    const saveEdit = () => {
      if (!isValidEdit) { return }
      if (editForm.id === null) {
        editForm.post('/config-usages', editFormOptions)
      } else {
        editForm.put(`/config-usages/${editForm.id}`, editFormOptions)
      }
    }
    const cancelEdit = () => {
      isEditDialogVisible.value = false
    }
    const deleteUsage = () => {

      // TODO: create real confirm
      if (confirm('really delete this?')) {
        editForm.delete(`/config-usages/${editForm.id}`, editFormOptions)
      }

    }

  // #endregion

  // #region data-table

    const tableheaders = ref([
      { title: 'Name', key: 'name', minWidth: '60%' },
      { title: 'Nur für Verantwortlichen', key: 'is_locked' },
      { title: 'Bearbeiten', key: 'action', sortable: false },
    ])

  // #endregion

// #endregion

</script>

<template>

  <Head title="Verwendungen" />

  <div class="app-ConfigUsages">
    
    <LcPagebar title="Verwendungen" @back="openInventory" />

    <div class="app-ConfigUsages--page">

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
        <template v-slot:item.is_locked="{ item }">
          <span>{{ item.is_locked ? 'Ja' : 'Nein' }}</span>
        </template>
        <template v-slot:item.action="{ item }">
          <v-btn small color="primary" @click="openEditUsageDialog(item)">
            <v-icon icon="mdi-cog"></v-icon>
          </v-btn>
        </template>
      </v-data-table>

    </div>
  </div>

  <v-dialog v-model="isEditDialogVisible" max-width="450px">
    <v-card prepend-icon="mdi-truck" :title="editForm.dialogTitle" :disabled="editForm.processing" class="rounded-0">

      <v-divider></v-divider>

      <v-card-text>
        <p class="mb-4">
          Eine Verwendung muss beim Buchen angegeben werden. <br>
          Du kannst auch einstellen, ob diese nur für den Verantwortlichen verfügbar sein soll.
        </p>
        <v-text-field
          v-model="editForm.name" id="id-editusage-name"
          label="Name" hide-details
          @keydown.enter="saveEdit">
        </v-text-field>
        <v-checkbox v-model="editForm.is_locked"
          label="Nur für den Verantwortlichen" hide-details></v-checkbox>

        <v-alert v-if="editForm.errors.name" type="error">
          {{ editForm.errors.name }}
        </v-alert>

      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="mx-4 mb-2">
        <v-btn color="error"
          variant="tonal" v-if="editForm.id !== null"
          @click="deleteUsage">Löschen</v-btn>
        <v-spacer></v-spacer>
        <v-btn @click="cancelEdit">Abbrechen</v-btn>
        <v-btn color="primary" :loading="editForm.processing"
          variant="tonal" :disabled="!isValidEdit"
          @click="saveEdit">Speichern</v-btn>
      </v-card-actions>

    </v-card>
  </v-dialog>

  <LcConfirm ref="confirm" :z="1" />
  
</template>
<style lang="scss" scoped>
.app-ConfigUsages {

  &--page {
    max-width: 850px;
    margin: .5rem auto;
  }

}
</style>
