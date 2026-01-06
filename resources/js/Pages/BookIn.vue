<script setup>

/**
 * Bookin - Page component
 *
 * This page loads open orders and show them in a timeline.
 * A delivery could be checked and adapted on demand.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local composables
  import { findOptimalSize } from '@/Utils/sizeUtils'
  import InputService from '@/Services/InputService'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcButton from '@/Components/LcButton.vue'
  import LcItemAmountDialog from '@/Dialogs/LcItemAmountDialog.vue'
  import LcConfirm from '@/Dialogs/LcConfirm.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'
  import IdleCursor from '@/Components/IdleCursor.vue'

// #endregion
// #region Props

  const props = defineProps({
    openOrders: {
      type: Array,
      required: true,
    },
  })

// #endregion
// #region Navigation

  // Routing-Events
  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  // ConfirmationDialog
  const confirmDialog = ref(null)

  // Routes
  async function openWelcome() {

    if (props.openOrders.some(o => o.changed)) {
      const confirmed = await confirmDialog.value.open({
        title: 'Abbrechen?',
        message: 'Du hast bereits etwas geändert! <br>Willst du <b>wirklich abbrechen?</b>',
      })
      if (!confirmed) { return }
    }
    router.get('/')

  }

  // #region Keyboard-Shortcuts

    const handleEscape = () => {
      if (!amountDialog.value.isVisible) {
        openWelcome()
      }
    }

  // #endregion

// #endregion

// #region BookIn-Logic

  // #region AmountAdapt

    const amountDialog = ref(null)

    const adaptOrder = async (order) => {
      const delivered = await amountDialog.value.open({
        title: 'Liefermenge',
        message: 'Gib die Menge an, die geliefert wurde.',
        sizes: order.item.sizes,
        selectedSize: order.item.sizes.find(e=>e.unit === order.ooSizeUnit),
        selectedAmount: order.ooSizeAmount,
      })
      if (delivered === null) { return }
      const propOrder = props.openOrders.find(o => o.id === order.id)
      if (!propOrder) { return }
      propOrder.amount_delivered = delivered
    }

  // #endregion
  // #region Finish

    // Form
    const bookinForm = useForm({
      orders: []
    })
    const bookinFormOptions = {
      preserveScroll: true,
      onSuccess: () => {
      },
    }

    // Post
    const finishOrders = () => {

      bookinForm.orders = props.openOrders.map(oo => {
        return {
          id: oo.id,
          amount_delivered: oo.changed ? oo.amount_delivered : oo.amount_desired
        }
      })
      bookinForm.post('/bookin', bookinFormOptions)

    }

  // #endregion

  // #region TemplateProps

    const hasOpenOrders = computed(() => props.openOrders.length>0)

    const groupedOpenOrders = computed(() => {

      const groups = props.openOrders.reduce((groups, order) => {
        const date = order.order_date;

        // Initialize date group if it doesn't exist
        if (!groups[date]) {
          groups[date] = {};
        }

        // Initialize demand group within date group if it doesn't exist
        const demandName = order.item.demand.name;
        if (!groups[date][demandName]) {
          groups[date][demandName] = [];
        }

        // Set additional properties on the order
        order.changed = order.amount_desired !== order.amount_delivered;
        if (order.amount_delivered > 0) {
          const calc = findOptimalSize(order.item.sizes, order.amount_delivered);
          order.ooSizeText = calc.text;
          order.ooSizeUnit = calc.unit;
          order.ooSizeAmount = calc.amount;
        } else {
          order.ooSizeText = 'Nicht Geliefert';
          order.ooSizeUnit = order.item.basesize.unit;
          order.ooSizeAmount = 0;
        }

        // Push the order into the respective demand group within the date group
        groups[date][demandName].push(order);
        return groups;
      }, {});

      // Sort the date groups, demand groups, and orders by the specified criteria
      const sortedGroups = Object.keys(groups)
      .sort((a, b) => new Date(a) - new Date(b)) // Sort dates ascending
      .reduce((sorted, date) => {
        // Sort each demand group by demand_id within each date
        sorted[date] = Object.keys(groups[date])
          .sort((demandA, demandB) => {
            // Access the demand_id of the first order in each demand group for sorting
            const demandIdA = groups[date][demandA][0].item.demand_id;
            const demandIdB = groups[date][demandB][0].item.demand_id;
            return demandIdA - demandIdB;
          })
          .reduce((sortedDemands, demandName) => {
            // Sort orders within each demand group by the "name" property
            sortedDemands[demandName] = groups[date][demandName].sort((orderA, orderB) =>
              orderA.item.name.localeCompare(orderB.item.name)
            );
            return sortedDemands;
          }, {});
        return sorted;
      }, {});
      return sortedGroups;
    });

    const getOrderDate = (dstr) => {
      return new Date(dstr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' })
    }

  // #endregion

// #endregion

// #region Lifecycle

  onMounted(() => {
    InputService.registerEsc(handleEscape)
  })
  onUnmounted(() => {
    InputService.unregisterEsc(handleEscape)
  })

// #endregion

</script>

<template>

  <Head title="Lieferung" />
  <IdleCursor />

  <div class="page-bookin">

    <LcPagebar title="Lieferung" :disabled="bookinForm.processing" @back="openWelcome"></LcPagebar>

    <section class="page-bookin__empty" v-if="!hasOpenOrders">
      <v-empty-state
        icon="mdi-invoice-remove-outline"
        title="Keine Bestellung auf dem Weg">
      </v-empty-state>
    </section>

    <section v-else>

      <LcButton
        class="page-bookin__finish" :loading="bookinForm.processing"
        type="primary" prepend-icon="mdi-invoice-check"
        @click="finishOrders">{{ bookinForm.processing ? 'Materialeingang buchen ...' : 'Materialmengen bestätigen' }}
      </LcButton>

      <v-timeline class="ml-4" side="end">

        <v-timeline-item v-for="(demands, date) in groupedOpenOrders"
          class="my-4">
          <template v-slot:opposite>
            <b>{{ getOrderDate(date) }}</b>
          </template>
          <v-card class="rounded-0" variant="outlined">
            <v-card-text>
              <template v-for="(orders, demand) in demands">
                <h3>{{ demand }}</h3>
                <v-row v-for="order in orders" :key="order.id"
                  class="page-bookin__order-row"
                  justify="space-between" align="center" dense>
                  <v-col cols="5">
                    <span>{{ order.item.name }}</span>
                  </v-col>
                  <v-col cols="4" class="text-right" :class="{ 'text-red': order.changed }">
                    <span class="font-weight-bold">{{ order.ooSizeText }}</span>
                  </v-col>
                  <v-col cols="3" class="text-right">
                    <v-btn small variant="outlined"
                      @click="adaptOrder(order)">
                      <v-icon icon="mdi-cog"></v-icon>
                    </v-btn>
                  </v-col>
                </v-row>
              </template>
            </v-card-text>
          </v-card>
        </v-timeline-item>
      </v-timeline>

    </section>

    <!-- Dialogs -->
    <LcConfirm ref="confirmDialog" />
    <LcItemAmountDialog ref="amountDialog" />
    <LcRouteOverlay v-show="isRouting" />

  </div>

</template>
<style lang="scss" scoped>
.page-bookin {

  &__empty {
    margin-top: 6rem !important;
  }

  &__finish {
    width: 100%;
    height: 6rem;
    margin-bottom: 0.5rem;
  }

  &__order-row {
    display: flex;
    min-width: 650px;
  }

}
</style>
