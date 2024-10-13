<script setup>

/**
 * WhereIs - Page component
 *
 * This page enables the user to search locations of items.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import { Head, router } from '@inertiajs/vue3'

  // Local composables
  import { useInventoryStore } from '@/Services/StoreService'
  import InputService from '@/Services/InputService'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcItemInput from '@/Components/LcItemInput.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'
  import IdleCursor from '@/Components/IdleCursor.vue'

// #endregion
// #region Props

  const inventoryStore = useInventoryStore()

// #endregion
// #region Navigation

  // Router-Events
  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  // Routes
  function openWelcome() {
    router.get('/')
  }

// #endregion

// #region Search-Logic

  const selectedItem = ref(null)

  const selectItem = (item) => {
    selectedItem.value = item
  }

// #endregion

// #region Lifecycle

  onMounted(() => {
    InputService.registerEsc(openWelcome)
    inventoryStore.fetchStore()
  })
  onUnmounted(() => {
    InputService.unregisterEsc(openWelcome)
  })

// #endregion

</script>

<template>

  <Head title="Wo ist?" />
  <IdleCursor />

  <div class="page-whereis">

    <LcPagebar title="Wo ist ... ?" @back="openWelcome"></LcPagebar>

    <section>

      <LcItemInput
        :result-specs="{ w: 850, i: 11 }"
        :allow-scan="false"
        @select-item="selectItem">
      </LcItemInput>

      <v-card v-if="selectedItem"
        class="page-whereis__item" variant="outlined">
        <v-card-text>
          <div class="page-whereis__item-title">
            {{ selectedItem.name }}
          </div>
          <v-divider class="my-4"></v-divider>
          <div class="page-whereis__item-coarse">
            <v-icon icon="mdi-domain"></v-icon>
            {{ selectedItem.location.room }}
          </div>
          <div class="page-whereis__item-cab" v-if="selectedItem.location.cab">
            <v-icon icon="mdi-fridge"></v-icon>
            {{ selectedItem.location.cab }}
          </div>
          <div class="page-whereis__item-exact" v-if="selectedItem.location.exact">
            <v-icon icon="mdi-archive-marker-outline"></v-icon>
            {{ selectedItem.location.exact }}
          </div>
        </v-card-text>
      </v-card>

    </section>

  </div>

  <LcRouteOverlay v-show="isRouting" />

</template>
<style lang="scss" scoped>
.page-whereis {

  &__item {
    margin-top: .5rem;
    border-radius: 0;
  }

  &__item-title {
    font-size: 2rem;
  }

  &__item-coarse,
  &__item-cab,
  &__item-exact {
    display: flex;
    gap: 0.5rem;
    font-size: 1.4rem;
  }

  &__item-cab,
  &__item-exact {
    margin-top: 4px;
    margin-left: 1rem;
    font-size: 1.2rem;
  }

  &__item-exact {
    margin-left: 2rem;
  }

}
</style>
