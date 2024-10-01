<script setup>

// #region props/emits

  const props = defineProps({
    active: {
      type: Boolean,
      required: true,
    },
  })

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
