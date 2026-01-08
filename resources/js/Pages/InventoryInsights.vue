<script setup>

/**
 * InventoryInsights - Page component
 *
 * This page shows inventory insights.
 *
 */

// #region Imports

  // Vue composables
  import { ref } from 'vue'
  import { Head, router } from '@inertiajs/vue3'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'

// #endregion
// #region Navigation

  // Router-Events
  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  // Routes
  function openInventory() {
    router.get('/inventory')
  }

// #endregion

</script>

<template>

  <Head title="Insights" />

  <div class="page-inventoryinsights">

    <LcPagebar title="Insights" @back="openInventory" />

    <section>
      <v-card variant="outlined">
        <v-card-text>
          <p class="text-body-1 mb-2">
            Hier erscheinen zukünftig die Inventory-Insights für Entnahmen und Trends.
          </p>
        </v-card-text>
      </v-card>
    </section>

  </div>

</template>
