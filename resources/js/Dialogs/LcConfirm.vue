<script setup>

/**
 * LcConfirm - Dialog component
 *
 * A dialog to get confirmation for an action.
 *
 * Props (via "open"):
 *  - title (String): Title of the dialog.
 *  - message (String): Message of the dialog.
 *  - icon (optional|String): Icon that is shown near the title.
 *
 * Returns (via promise):
 *  - Boolean, if confirmed or not.
 *
 */

// #region Imports

  // Vue composables
  import InputService from '@/Services/InputService'
import { onMounted, onUnmounted, ref } from 'vue'

// #endregion

// #region Dialog-Logic

  // DialogProps
  const isVisible = ref(false)
  const title = ref('')
  const message = ref('')
  const icon = ref('')
  let resolvePromise = null;

  // DialogMethods
  const open = async (args) => {

    title.value = args?.title ?? 'Wirklich?'
    message.value = args?.message ?? ''
    icon.value = args?.icon ?? ''

    isVisible.value = true

    return new Promise((resolve) => {
      resolvePromise = resolve
    })

  }

  const accept = () => {
    isVisible.value = false
    resolvePromise(true)
  }
  const cancel = () => {
    isVisible.value = false
    resolvePromise(false)
  }

  const handleEscape = (e) => {
    if (!isVisible.value) { return }
    e.canceled = true
    cancel()
  }

  const handleEnter = (e) => {
    if (!isVisible.value) { return }
    e.canceled = true
    accept()
  }

// #endregion
// #region Lifecycle

  onMounted(() => {
    InputService.registerEsc(handleEscape)
    InputService.registerEnter(handleEnter)
  })
  onUnmounted(() => {
    InputService.unregisterEsc(handleEscape)
    InputService.unregisterEnter(handleEnter)
  })

// #endregion
// #region Expose

  defineExpose({ open })

// #endregion

</script>
<template>
  <v-dialog v-model="isVisible" max-width="450px" :persistent="true" style="z-index: 1000;">
    <v-card :prepend-icon="icon" :title="title" class="rounded-0">
      <v-divider></v-divider>
      <v-card-text>
        <p v-html="message"></p>
      </v-card-text>
      <v-divider></v-divider>
      <v-card-actions class="mx-4 mb-2">
        <v-btn
          @click="cancel">Nein
        </v-btn>
        <v-btn color="primary" variant="tonal"
          @click="accept">Ja
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
