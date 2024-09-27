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
    background: var(--lc-main-text);
    border-color: var(--lc-main-text);
    color: var(--lc-main-background);
    &:hover,
    &:focus-visible {
      color: var(--lc-main-text);
      background: var(--lc-main-background);
      & :deep(kbd) {
        outline: 2px solid var(--lc-main-text);
        color: var(--lc-main-text);
        box-shadow: 4px 4px var(--lc-main-text);
      }
    }
    & .lc-button--icon {
      font-size: 5rem;
    }
  }

  &--secondary {
    gap: 0;
    background: var(--lc-primary-accent-background);
    border-color: var(--lc-primary-accent-background);
    color: var(--lc-primary-accent-text);
    &:hover,
    &:focus-visible {
      color: var(--lc-primary-accent-background);
      background: var(--lc-primary-accent-text);
      & :deep(kbd) {
        outline: 2px solid var(--lc-primary-accent-background);
        color: var(--lc-primary-accent-background);
        box-shadow: 4px 4px var(--lc-primary-accent-background);
      }
    }
    & .lc-button--icon {
      font-size: 3rem;
    }
  }

  &--tertiary {
    background: var(--lc-secondary-accent-background);
    border-color: var(--lc-secondary-accent-background);
    color: var(--lc-secondary-accent-text);
    &:hover,
    &:focus-visible {
      color: var(--lc-secondary-accent-background);
      background: var(--lc-secondary-accent-text);
    }
    & .lc-button--icon {
      display: none;
    }
  }

  & :deep(kbd) {
    font-size: 0.7em;
    outline: 2px solid #fff;
    color: #fff;
    padding: .2rem;
    height: 80%;
    margin-left: .5rem;
    box-shadow: 4px 4px #fff;
  }

}
</style>
