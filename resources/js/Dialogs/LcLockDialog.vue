<script setup>

// #region imports

  // Vue composables
  import { ref, computed, watch } from 'vue'
  import { router } from '@inertiajs/vue3'

// #endregion

// #region dialog

  // props
  const isVisible = ref(false)

  // methods
  const open = () => {

    isVisible.value = true
    router.post('/', { action: 'LOCK' }, {

      onFinish: visit => {
        isVisible.value = false
      },

    })

  }

// #endregion

// #region expose

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="isVisible" max-width="450px" :persistent="true" @after-leave="cancel">
    <v-card prepend-icon="mdi-lock" class="rounded-0" title="Lagerverwaltung sperren">
      <v-divider></v-divider>
      <v-card-text class="d-flex justify-center">
        <v-progress-circular indeterminate></v-progress-circular>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>
