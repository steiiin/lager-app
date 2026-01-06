<script setup>

/**
 * LcTrend - Component
 *
 * A simple arrow that indicates a percentage of a trend.
 *
 * Props:
 *  - trend (Number): 0-x%
 *
 */

// #region Imports

  import { computed } from 'vue'

// #endregion
// #region Props

  const props = defineProps({
    trend: Number,
  })

// #endregion

// #region TemplateProps

  const clampedTrend = computed(() => Math.max(-100, Math.min(props.trend, 100)))

  const computedTransform = computed(() => {
    const desiredAngle = (clampedTrend.value / 100) * 90
    const rotation = -desiredAngle + 90
    return `rotate(${rotation}deg)`
  })

// #endregion

</script>
<template>
  <div class="lc-trend">
    <v-icon icon="mdi-arrow-up" :style="{ transform: computedTransform }"></v-icon>
  </div>
</template>
<style lang="scss" scoped>
.lc-trend
{
  display: inline-block;
}
</style>
