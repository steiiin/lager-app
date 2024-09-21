<script setup>

// #region imports

  // Vue composables
  import { ref, computed } from 'vue'
  import { Head, router } from '@inertiajs/vue3'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcItemInput from '@/Components/LcItemInput.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'

// #endregion

// #region props

  defineProps({
    items: {
      type: Array,
      required: true,
    },
  })

// #endregion

// #region navigation

  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  function openWelcome() {
    router.get('/')
  }

// #endregion

// #region item-selection

  const selectedItem = ref(null)
  const isItemSelected = computed(() => !!selectedItem.value)

  const selectItem = (item) => {
    selectedItem.value = item
  }

// #endregion

</script>

<template>

  <Head title="Wo ist?" />

  <div class="app-WhereIs">

    <LcPagebar title="Wo ist ... ?" @back="openWelcome"></LcPagebar>
    
    <div class="app-WhereIs--page">

      <LcItemInput 
        :items="items" :result-pos="{ w: 850, i: 11 }"
        :allowScan="false" 
        @select-item="selectItem">
      </LcItemInput>

      <v-card class="app-WhereIs--selected mt-2 rounded-0" variant="outlined" v-if="isItemSelected">
        <v-card-text>
          <div class="app-WhereIs--selected-title">
            {{ selectedItem.name }}
          </div>
          <v-divider class="my-4"></v-divider>
          <div class="app-WhereIs--selected-coarse">
            <v-icon icon="mdi-domain"></v-icon>
            {{ selectedItem.location.room }}
          </div>
          <div class="app-WhereIs--selected-cab mt-2" v-if="!!selectedItem.location.cab">
            <v-icon icon="mdi-fridge"></v-icon> 
            {{ selectedItem.location.cab }}
          </div>
          <div class="app-WhereIs--selected-exact mt-2" v-if="!!selectedItem.location.exact">
            <v-icon icon="mdi-archive-marker-outline"></v-icon>
            {{ selectedItem.location.exact }}
          </div>
        </v-card-text>
      </v-card>

    </div>
  </div>

  <LcRouteOverlay v-show="isRouting" />

</template>
<style lang="scss" scoped>
.app-WhereIs {
  &--page {
    max-width: 850px;
    margin: 0.5rem auto;
  }

  &--selected-title {
    font-size: 2rem;
  }

  &--selected-coarse,
  &--selected-cab,
  &--selected-exact {
    display: flex;
    gap: 0.5rem;
  }

  &--selected-coarse {
    font-size: 1.4rem;
  }

  &--selected-cab,
  &--selected-exact {
    font-size: 1.2rem;
  }

  &--selected-cab {
    margin-left: 1rem;
  }

  &--selected-exact {
    margin-left: 2rem;
  }
}
</style>
