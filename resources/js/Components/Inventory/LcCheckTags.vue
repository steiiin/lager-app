<script setup>

/**
 * LcInventoryCheckTags - Component
 *
 * A list of tags.
 *
 * Props:
 *  - tags (array)
 *
 */

// #region Imports

  import { computed } from 'vue'

// #endregion
// #region Props

  const props = defineProps({
    tags: Array,
  })

// #endregion

const hasCheck = computed(() => !!check.value)
const hasExpiry = computed(() => !!expiry.value)
const hasStrictCheck = computed(() => props.tags.some(e => e.type == 'strict_check'))

const check = computed(() => props.tags.find(e => e.type == 'check'))
const expiry = computed(() => props.tags.find(e => e.type == 'expiry'))

</script>
<template>
  <div class="lc-inventory-tags">
    <v-chip v-if="hasCheck"
      prepend-icon="mdi-clipboard-clock"
      color="success">{{ check.label }}
    </v-chip>
    <v-chip v-if="hasExpiry"
      prepend-icon="mdi-timer-sand"
      color="error">{{ expiry.label }}
    </v-chip>
    <v-chip v-if="hasStrictCheck"
      prepend-icon="mdi-alert"
      color="danger">Auffällig</v-chip>
  </div>
</template>
<style lang="scss" scoped>
.lc-inventory-tags {
  display: flex;
  gap: 4px;
  justify-content: right;
}
</style>
