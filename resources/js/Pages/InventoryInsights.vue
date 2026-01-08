<script setup>

/**
 * InventoryInsights - Page component
 *
 * This page shows inventory insights.
 *
 */

// #region Imports

  // Vue composables
  import { computed, ref } from 'vue'
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
// #region Props + Data

  const props = defineProps({
    lowScanSignals: {
      type: Object,
      default: () => ({ all: [], hyg: [] }),
    },
  })

  const sortSignals = (signals) => [...signals]
    .sort((a, b) => new Date(b.date) - new Date(a.date))

  const allSignals = computed(() => sortSignals(props.lowScanSignals.all ?? []))
  const hygSignals = computed(() => sortSignals(props.lowScanSignals.hyg ?? []))

  const formatDate = (dateString) => {
    if (!dateString) { return 'Unbekannt' }
    const date = new Date(dateString)
    const day = date.getDate()
    const month = date.getMonth() + 1
    const year = date.getFullYear()
    return `${day}.${month}.${year}`
  }

  const formatDelta = (signal, key) => {
    const current = Number(signal[key] ?? 0)
    const avg = Number(signal[`avg_${key.split('_')[1]}`] ?? 0)
    if (avg === 0) { return '0' }
    const diff = ((current - avg) / avg) * 100
    return `${diff.toFixed(0)}%`
  }

// #endregion

</script>

<template>

  <Head title="Insights" />

  <div class="page-inventoryinsights">

    <LcPagebar title="Insights" @back="openInventory" />

    <section>
      <v-row>
        <v-col cols="12" md="6">
          <v-card variant="outlined">
            <v-card-title class="d-flex align-center justify-space-between">
              <span>Niedrige Scans (alle Entnahmen)</span>
              <v-chip size="small" color="primary" variant="outlined">
                {{ allSignals.length }}
              </v-chip>
            </v-card-title>
            <v-divider></v-divider>
            <v-card-text>
              <v-list density="compact" v-if="allSignals.length">
                <v-list-item
                  v-for="signal in allSignals"
                  :key="`${signal.shift}-${signal.date}`"
                  class="px-0">
                  <v-list-item-title class="d-flex justify-space-between">
                    <span>{{ formatDate(signal.date) }} · {{ signal.shift }}</span>
                    <span class="text-caption">
                      {{ signal.amount_all }} vs Ø {{ Number(signal.avg_all ?? 0).toFixed(1) }}
                    </span>
                  </v-list-item-title>
                  <v-list-item-subtitle class="text-caption">
                    Abweichung {{ formatDelta(signal, 'amount_all') }}
                  </v-list-item-subtitle>
                </v-list-item>
              </v-list>
              <p v-else class="text-body-2 text-medium-emphasis">
                Keine Auffälligkeiten im aktuellen Zeitraum.
              </p>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card variant="outlined">
            <v-card-title class="d-flex align-center justify-space-between">
              <span>Niedrige Scans (Hygiene)</span>
              <v-chip size="small" color="primary" variant="outlined">
                {{ hygSignals.length }}
              </v-chip>
            </v-card-title>
            <v-divider></v-divider>
            <v-card-text>
              <v-list density="compact" v-if="hygSignals.length">
                <v-list-item
                  v-for="signal in hygSignals"
                  :key="`${signal.shift}-${signal.date}`"
                  class="px-0">
                  <v-list-item-title class="d-flex justify-space-between">
                    <span>{{ formatDate(signal.date) }} · {{ signal.shift }}</span>
                    <span class="text-caption">
                      {{ signal.amount_hyg }} vs Ø {{ Number(signal.avg_hyg ?? 0).toFixed(1) }}
                    </span>
                  </v-list-item-title>
                  <v-list-item-subtitle class="text-caption">
                    Abweichung {{ formatDelta(signal, 'amount_hyg') }}
                  </v-list-item-subtitle>
                </v-list-item>
              </v-list>
              <p v-else class="text-body-2 text-medium-emphasis">
                Keine Auffälligkeiten im aktuellen Zeitraum.
              </p>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </section>

  </div>

</template>
