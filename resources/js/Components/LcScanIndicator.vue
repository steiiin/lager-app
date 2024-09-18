<script setup>

// #region imports

  // Vue composables
  import { ref, onMounted, onBeforeUnmount } from 'vue'

// #endregion

// #region props/emits

  const props = defineProps({
    active: {
      type: Boolean,
      required: true,
    },
  })
  const emit = defineEmits([
    'scan',
  ])

// #endregion

// #region scanner-tracker

  onMounted(() => {
    document.body.addEventListener('keydown', trackKeyInput)
  })
  onBeforeUnmount(() => {
    document.body.removeEventListener('keydown', trackKeyInput)
  })

  let trackerBuffer = ''
  let trackerTimer = null
  const trackKeyInput = (e) => {

    if (e.key.length === 1) {
      trackerBuffer += e.key
    } else if (e.key === 'Enter') {

      e.stopImmediatePropagation()

      // check code
      if (trackerBuffer.startsWith('LC-'))
      {
        emit('scan', trackerBuffer)
      }

      // reset tracker
      trackerBuffer = ''
      clearTimeout(trackerTimer)
      return

    }

    // restart timeout
    if (trackerTimer) {
      clearTimeout(trackerTimer)
    }
    trackerTimer = setTimeout(() => {
      trackerBuffer = ''
    }, 50)

  }

// #endregion

// #region expose

  defineExpose({ trackKeyInput })

// #endregion

</script>
<template>
  <div class="lc-scanindicator">
    <v-icon v-if="active" icon="mdi-barcode-scan" size="36"></v-icon>
    <v-progress-circular v-if="active" size="72" indeterminate="disable-shrink"></v-progress-circular>
    <v-progress-circular v-else size="72"></v-progress-circular>
  </div>
</template>
<style lang="scss" scoped>
.lc-scanindicator {

  position: relative;
  width: 72px;
  height: 72px;

  & > * {
    position: absolute;
  }

  & .v-icon {
    left: 19px;
    top: 19px;
  }


}
</style>
