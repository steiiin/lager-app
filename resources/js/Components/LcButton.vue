<script setup>

// #region props

  defineProps({
    type: {
      type: String,
      required: false,
      default: 'secondary',
    },
    icon: {
      type: String,
      required: false,
      default: null,
    },
    onlyIcon: {
      type: String,
      required: false,
      default: null,
    },
    prependIcon: {
      type: String,
      required: false,
      default: null,
    },
    loading: {
      type: Boolean,
      required: false,
      default: false,
    },
  })

// #endregion

</script>
<template>
  <button class="lc-button"
    :class="{
      'lc-button--primary': type === 'primary',
      'lc-button--secondary': type === 'secondary',
      'lc-button--tertiary': type === 'tertiary',
      'lc-button--loading': loading,
    }">
    <div class="lc-button--icon" v-if="!!icon">
      <v-icon :icon="icon"></v-icon>
    </div>
    <div class="lc-button--standaloneicon" v-if="!!onlyIcon">
      <v-icon :icon="onlyIcon"></v-icon>
    </div>
    <div class="lc-button--content">
      <template v-if="!!prependIcon">
        <v-icon
          :icon="prependIcon">
        </v-icon>
      </template>

      <slot v-if="!onlyIcon" />
    </div>
    <v-progress-linear class="lc-button--progress"
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

  &--icon {
    font-size: 4rem;
  }
  &--standaloneicon {
    font-size: 1.5rem;
  }
  &--content {
    display: flex;
    gap: .5rem;
    align-items: center;

    &-progress {
      height: 1.5rem;
    }
  }

  &--loading {
    pointer-events: none;
  }
  &--progress {
    position: absolute;
  }

  &--primary {
    background: var(--main-dark);
    border-color: var(--main-dark);
    color: var(--main-light);
    &:hover,
    &:focus-visible {
      color: var(--main-dark);
      background: var(--main-light);
      & :deep(kbd) {
        outline: 2px solid var(--main-dark);
        color: var(--main-dark);
        box-shadow: 4px 4px var(--main-dark);
      }
    }
    & .lc-button--icon {
      font-size: 5rem;
    }
  }

  &--secondary {
    gap: 0;
    background: var(--accent-primary-background);
    border-color: var(--accent-primary-background);
    color: var(--accent-primary-foreground);
    &:hover,
    &:focus-visible {
      color: var(--accent-primary-background);
      background: var(--accent-primary-foreground);
      & :deep(kbd) {
        outline: 2px solid var(--accent-primary-background);
        color: var(--accent-primary-background);
        box-shadow: 4px 4px var(--accent-primary-background);
      }
    }
    & .lc-button--icon {
      font-size: 3rem;
    }
  }

  &--tertiary {
    background: var(--accent-secondary-background);
    border-color: var(--accent-secondary-background);
    color: var(--accent-secondary-foreground);
    &:hover,
    &:focus-visible {
      color: var(--accent-secondary-background);
      background: var(--accent-secondary-foreground);
    }
    & .lc-button--icon {
      display: none;
    }
  }

  & :deep(kbd) {
    font-size: 0.6em;
    outline: 2px solid var(--accent-primary-foreground);
    color: var(--accent-primary-foreground);
    padding: 0 .3rem;
    height: 60%;
    margin-left: .3rem;
    box-shadow: 4px 4px var(--accent-primary-foreground);
  }

}
</style>
