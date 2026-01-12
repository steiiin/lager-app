<script setup>

/**
 * LcPagebar - Component
 *
 * The app's app-titlebar.
 *
 * Props:
 *  - title (String): The page title.
 *  - disabled (Boolean): Disables the back-button.
 *
 * Emits:
 *  - back: Emitted when the user clicks the back-button.
 *
 */

// #region Props

  const props = defineProps({
    title: {
      type: String,
      required: true,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  })

// #endregion
// #region Emits

  const emit = defineEmits([
    'back'
  ])

// #endregion
</script>
<template>

    <div class="lc-pagebar">
      <section class="lc-pagebar__content">
        <button class="lc-pagebar__content--backbutton" @click="emit('back')" v-if="!disabled">
          <div class="lc-pagebar__content--backbutton-icon">
            <v-icon icon="mdi-arrow-left"></v-icon>
          </div>
        </button>
        <div class="lc-pagebar__content--title">
          {{ title }}
        </div>
        <v-spacer></v-spacer>
        <div class="lc-pagebar__content--actions">
          <slot name="actions" />
        </div>
      </section>
    </div>

</template>
<style lang="scss" scoped>
.lc-pagebar {

  background: var(--main-dark);

  &__content {

    margin: 0 auto;
    display: flex;
    flex-direction: row;
    outline: .5rem solid var(--main-light);
    height: 6rem;
    padding: 0;

    &--backbutton {

      display: flex;
      justify-content: center;
      align-items: center;
      width: 6rem;
      height: 6rem;

      outline: .5rem solid var(--main-light);
      transition: background-color 0.5s ease, color 0.3s ease;

      &-icon {
        color: var(--main-light);
        font-size: 2rem;
      }

    }

    &--title {
      color: var(--main-light);
      font-size: 1.25rem;
      font-weight: bold;
      display: flex;
      align-items: center;
      margin-left: 2rem;
    }

    &--actions {
      display: flex;
      align-self: center;
      align-items: center;
      gap: .5rem;
      margin-right: 2rem;
      height: calc(100% - .5rem);
    }

  }

}
body:not(.cursor-off) .lc-pagebar {
  &__content {
    &--backbutton:hover {
      background-color: var(--main-light);
      border: .5rem solid var(--main-dark);
      & .lc-pagebar__content--backbutton-icon {
        color: var(--main-dark);
      }
    }
  }
}
</style>
