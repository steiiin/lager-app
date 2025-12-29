<script setup>

/**
 * LcButton - Component
 *
 * A button component that seemlessly integrates with the app's style.
 * It supports different button types, icons and loading indication.
 *
 * Props:
 *  - type (String): Defines the visual style. [ 'primary', 'secondary' ]
 *  - loading (Bool): Indicates if a progress indicator is shown.
 *  - icon (String): Renders a "mdi"-icon over the button's text.
 *  - prependIcon (String): Same as "icon", but it is rendered before the text in the same line.
 *
 */

// #region Imports

  import { computed } from 'vue'

// #endregion
// #region Props

  const props = defineProps({
    type: {
      type: String,
      default: 'secondary',
      validator: value => ['primary', 'secondary'].includes(value),
    },
    selected: {
      type: Boolean,
      default: false,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    icon: String,
    prependIcon: String,
  })

// #endregion

// #region TemplateProps

  const buttonClasses = computed(() => ({
    'lc-button--primary': props.type === 'primary',
    'lc-button--secondary': props.type === 'secondary',
    'lc-button--loading': props.loading,
    'selected': props.selected,
  }))

// #endregion

</script>
<template>
  <button class="lc-button" :class="buttonClasses">
    <div class="lc-button__icon" v-if="icon">
      <v-icon :icon="icon"></v-icon>
    </div>
    <div class="lc-button__content">
      <template v-if="prependIcon">
        <v-icon
          :icon="prependIcon">
        </v-icon>
      </template>
      <slot />
    </div>
    <v-progress-linear class="lc-button__progress"
      indeterminate v-if="loading">
    </v-progress-linear>
  </button>
</template>
<style lang="scss" scoped>
.lc-button {

  text-transform: uppercase;
  font-weight: bold;
  font-size: 1rem;
  letter-spacing: 1px;
  border: .5rem solid;

  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 1rem;

  transition: background-color 0.3s ease, color 0.3s ease;

  &__icon {
    font-size: 4rem;
  }
  &__content {
    display: flex;
    gap: .5rem;
    align-items: center;
  }
  &__progress {
    position: absolute;
  }

  &--loading {
    pointer-events: none;
  }
  &--primary {

    background: var(--main-dark);
    border-color: var(--main-dark);
    color: var(--main-light);

    &:hover,
    &:focus-visible,
    &.selected {
      color: var(--main-dark);
      background: var(--main-light);
      & :deep(kbd) {
        outline: 2px solid var(--main-dark);
        color: var(--main-dark);
        box-shadow: 4px 4px var(--main-dark);
      }
    }

    & .lc-button__icon {
      font-size: 5rem;
    }

  }
  &--secondary {

    gap: 0;
    background: var(--accent-primary-background);
    border-color: var(--accent-primary-background);
    color: var(--accent-primary-foreground);

    &:hover,
    &:focus-visible,
    &.selected {
      color: var(--accent-primary-background);
      background: var(--accent-primary-foreground);
      & :deep(kbd) {
        outline: 2px solid var(--accent-primary-background);
        color: var(--accent-primary-background);
        box-shadow: 4px 4px var(--accent-primary-background);
      }
    }

    & .lc-button__icon {
      font-size: 3rem;
    }

  }

  & :deep(kbd) {

    outline: 2px solid var(--accent-primary-foreground);
    box-shadow: 4px 4px var(--accent-primary-foreground);
    color: var(--accent-primary-foreground);

    padding: 0 .3rem;
    height: 60%;
    margin-left: .3rem;

    font-size: 0.6em;

  }

}
</style>
