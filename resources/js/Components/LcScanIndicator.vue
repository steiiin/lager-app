<script setup>

/**
 * LcScanIndicator - Component
 *
 * An indicator to show the user the scanner is active.
 *
 * Props:
 *  - active (Boolean): If the indicator is running or shown as inactive.
 *
 */

// #region Imports

  // Vue composables
  import { computed } from 'vue'

// #endregion
// #region Props

  const props = defineProps({
    active: {
      type: Boolean,
      required: true,
    },
  })

  // #region TemplateProps

    const indeterminateState = computed(() => props.active ? 'disable-shrink' : false)

  // #endregion

// #endregion

</script>
<template>
  <div class="lc-scanindicator">
    <v-icon v-show="active" icon="mdi-barcode-scan" size="36"></v-icon>
    <v-progress-circular size="72" :indeterminate="indeterminateState"></v-progress-circular>
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
