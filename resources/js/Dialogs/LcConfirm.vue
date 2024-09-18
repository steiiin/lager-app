<script setup>

// #region imports

  // Vue composables
  import { ref } from 'vue'

// #endregion

// #region props

  defineProps({
    z: {
      type: Number,
      required: false,
      default: 1000,
    },
  })

// #endregion

// #region dialog

  // props
  const isVisible = ref(false)
  const title = ref('')
  const message = ref('')
  const icon = ref('')
  let resolvePromise = null;

  // methods
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


// #endregion

// #region expose

  defineExpose({ open })

// #endregion

</script>
<template>
  <v-dialog v-model="isVisible" max-width="450px" :persistent="true" :style="'z-index:'+z">
    <v-card :prepend-icon="icon" :title="title" class="rounded-0">
      <v-divider></v-divider>
      <v-card-text>
        <p v-html="message"></p>
      </v-card-text>
      <v-divider></v-divider>
      <v-card-actions class="mx-4 mb-2">
        <v-btn @click="cancel">Nein</v-btn>
        <v-btn color="primary" variant="tonal" 
          @click="accept">Ja
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
