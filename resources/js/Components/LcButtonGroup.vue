<script setup>

/**
 * LcButtonGroup - Component
 *
 * A toggle group to switch between multiple states
 *
 * Props:
 *  - type (String): Defines the visual style. [ 'primary', 'secondary' ]
 *  - loading (Bool): Indicates if a progress indicator is shown.
 *  - icon (String): Renders a "mdi"-icon over the button's text.
 *  - prependIcon (String): Same as "icon", but it is rendered before the text in the same line.
 *
 */

// #region Imports

  import { ref, watch } from 'vue'
  import LcButton from './LcButton.vue'

// #endregion
// #region Props

  const props = defineProps({
    modelValue: String,
    items: {
      type: Array,
      required: true,
    },
  })
  const emit = defineEmits([
    'update:modelValue'
  ])

// #endregion

const currentValue = ref(props.modelValue)
watch(
  () => props.modelValue,
  val => (currentValue.value = val),
  { immediate: true }
)

const switchValue = (value) => {
  currentValue.value = value
  emit('update:modelValue', value)
}

</script>
<template>
  <div class="lc-button-group">
    <LcButton v-for="item in items" :key="item.value"
      class="lc-button-group__btn" :type="item.value == currentValue ? 'primary' : 'secondary'"
      @click="switchValue(item.value)">{{ item.label }}
    </LcButton>
  </div>
</template>
<style lang="scss" scoped>
.lc-button-group {

    margin-top: .5rem;
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: .5rem;
    justify-content: space-between;

    &__btn {

      height: 4rem;
      min-width: 6rem;
      flex: 1;

    }

  }
</style>
