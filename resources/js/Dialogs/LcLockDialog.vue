<script setup>

/**
 * LcLockDialog - Dialog component
 *
 * A dialog that is opened while locking the app.
 *
 */

// #region Imports

  // Vue composables
  import { ref } from 'vue'
  import { router } from '@inertiajs/vue3'

// #endregion

// #region Lock-Logic

  // DialogProps
  const isVisible = ref(false)

  // DialogMethod
  const open = () => {

    isVisible.value = true
    router.post('/', { action: 'LOCK' }, {

      onFinish: () => {
        isVisible.value = false
      },

    })

  }

// #endregion

// #region Expose

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="isVisible" max-width="450px" :persistent="true">
    <v-card prepend-icon="mdi-lock" class="rounded-0" title="Lagerverwaltung sperren">
      <v-divider></v-divider>
      <v-card-text class="d-flex justify-center">
        <v-progress-circular indeterminate></v-progress-circular>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>
