<script setup>

// #region imports

  // Vue composables
  import { onMounted, onUnmounted } from 'vue'

// #endregion

// #region emit/props

  const emit = defineEmits([
    'back'
  ])
  const props = defineProps({
    title: {
      type: String,
      required: true,
    },
    disabled: {
      type: Boolean,
      required: false,
      default: false,
    },
    width: {
      type: Number,
      required: false,
      default: 850,
    },
  })

// #endregion

</script>
<template>
  
    <div class="lc-Pagebar">
      <div class="lc-Pagebar--content" :style="'max-width:'+width+'px'">
        <button class="lc-Pagebar--button" @click="emit('back')" v-if="!disabled">
          <div class="lc-Pagebar--button-icon">
            <v-icon icon="mdi-arrow-left"></v-icon>
          </div>
        </button>
        <div class="lc-Pagebar--title">
          {{ title }}
        </div>
        <v-spacer></v-spacer>
        <div class="lc-Pagebar--actions">
          <slot name="actions" />
        </div>
      </div>
    </div>
    
</template>
<style lang="scss" scoped>
.lc-Pagebar {

  background: var(--main-dark);

  &--content {
    margin: 0 auto;
    display: flex;
    flex-direction: row;
    outline: .5rem solid var(--main-light);
    height: 6rem;
  }

  &--button {
    outline: .5rem solid var(--main-light);
    
    // box-sizing: content-box;
    
    display: flex;
    justify-content: center;
    align-items: center;
    width: 6rem;
    height: 6rem;

    transition: background-color 0.5s ease, color 0.3s ease;

    &-icon {
      color: var(--main-light);
      font-size: 2rem;
    }

    &:hover {
      background-color: var(--main-light);
      border: .5rem solid var(--main-dark);
      & .lc-Pagebar--button-icon {
        color: var(--main-dark);
      }
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
</style>
