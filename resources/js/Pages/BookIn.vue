<script setup>

// #region imports

  // Vue composables
  import { ref, computed, onMounted,toRef } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local composables
  import { findOptimalSize } from '@/Composables/CalcSizes'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcItemInput from '@/Components/LcItemInput.vue'
  import LcUsageInput from '@/Components/LcUsageInput.vue'
  import LcButton from '@/Components/LcButton.vue'
  import LcCalcMinMaxDialog from '@/Dialogs/LcCalcMinMaxDialog.vue'
  import LcConfirm from '@/Dialogs/LcConfirm.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'

// #endregion

// #region props

  const props = defineProps({
    openOrders: {
      type: Array,
      required: true,
    },
    isUnlocked: {
      type: Boolean,
      default: false,
    },
  })

// #endregion

// #region navigation

  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  const confirmDialog = ref(null)

  async function openWelcome() {

    // if (hasAnyBookings.value) {
    //   const confirmed = await confirmDialog.value.open({
    //     title: 'Abbrechen?',
    //     message: 'Du hast bereits Material hinzugefügt! <br>Willst du die Entnahme <b>wirklich abbrechen?</b>',
    //   })
    //   if (!confirmed) { return }
    // }
    router.get('/')

  }

// #endregion

// #region orders

  const hasAnyOpenOrders = computed(() => props.openOrders.length>0)

  // #region order-data

    const groupedOpenOrders = computed(() => {
      return props.openOrders.reduce((groups, order) => {
        const date = order.updated_at //.split('T')[0]
        if (!groups[date]) {
          groups[date] = []
        }
        order.changed = order.amount_delivered>0 && order.amount_desired !== order.amount_delivered
        const calc = findOptimalSize(order.item.sizes, order.changed ? order.amount_delivered : order.amount_desired)
        order.ooSizeText = calc.text
        order.ooSizeUnit = calc.unit
        order.ooSizeAmount = calc.amount
        groups[date].push(order)
        return groups
      }, {})
    })

    const getOoDate = (dstr) => {
      return new Date(dstr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' })
    }

  // #endregion

  // #region adapt

    const minmaxCalc = ref(null)

    const adaptOrder = async (order) => {
      const delivered = await minmaxCalc.value.open({
        title: 'Liefermenge',
        message: 'Gib die Menge an, die geliefert wurde.',
        sizes: order.item.sizes,
        allowZero: true,
        curSize: order.item.sizes.find(e=>e.unit === order.ooSizeUnit),
        curAmount: order.ooSizeAmount,
      })
      if (delivered === null) { return }
      const propOrder = props.openOrders.find(o => o.id === order.id)
      if (!propOrder) { return }
      propOrder.amount_delivered = delivered
    }

    const ordersForm = useForm({
      orders: []
    })
    const ordersFormOptions = {
      preserveScroll: true,
      onSuccess: () => {
      },
    }
    const finishOrders = () => {

      ordersForm.orders = props.openOrders.map(oo => { 
        return { 
          id: oo.id, 
          amount_delivered: oo.changed ? oo.amount_delivered : oo.amount_desired 
        }   
      })
      ordersForm.post('/bookin', ordersFormOptions)

    }

  // #endregion

// #endregion

</script>

<template>

  <Head title="Lieferung" />

  <div class="app-BookIn">

    <LcPagebar title="Lieferung" :disabled="ordersForm.processing" @back="openWelcome"></LcPagebar>

    <div class="app-BookIn--page">

      <div class="app-BookIn--table">

        <v-empty-state v-if="!hasAnyOpenOrders"
          icon="mdi-invoice-remove-outline"
          title="Keine Bestellung auf dem Weg">
          <template #text>
            Es sind keine offenen Bestellungen im System. <br>
            Einen Verlauf kannst du am Wachen-PC abrufen.
          </template>
        </v-empty-state>

        <template v-else>
        
          <LcButton
            class="app-BookIn--finishBlock" :loading="ordersForm.processing"
            type="primary" prepend-icon="mdi-invoice-check"
            @click="finishOrders">{{ ordersForm.processing ? 'Materialeingang buchen ...' : 'Materialmengen bestätigen' }}
          </LcButton>

          <v-timeline class="ml-4"
            side="end">
        
            <v-timeline-item
              v-for="(orders, date) in groupedOpenOrders"
              class="my-4">
              <template v-slot:opposite>
                <b>{{ getOoDate(date) }}</b>
              </template>
              <v-card class="rounded-0" variant="outlined">
                <v-card-text>
                  <v-row
                    v-for="order in orders" :key="order.id"
                    class="app-BookIn--adapt-row" 
                    justify="space-between" align="center" dense>
                    <!-- Item Name -->
                    <v-col cols="6">
                      <span class="font-weight-bold">{{ order.item.name }}</span>
                    </v-col>

                    <!-- Ordered Amount -->
                    <v-col cols="2" class="text-right" :class="{ 'text-red': order.changed }">
                      <span class="font-weight-bold">{{ order.ooSizeText }}</span>
                    </v-col>

                    <!-- Cog Icon Button -->
                    <v-col cols="3" class="text-right">
                      <v-btn small variant="outlined" @click="adaptOrder(order)">
                        <v-icon icon="mdi-cog"></v-icon>
                      </v-btn>
                    </v-col>
                  </v-row>
                </v-card-text>
              </v-card>
            </v-timeline-item>

          </v-timeline>
          
        </template>

      </div>

    </div>

    <!-- Dialogs -->
    <LcConfirm ref="confirmDialog" />
    <LcCalcMinMaxDialog ref="minmaxCalc" />
    <LcRouteOverlay v-show="isRouting" />

  </div>

</template>
<style lang="scss" scoped>
.app-BookIn {

  &--page {
    max-width: 850px;
    margin: 0.5rem auto;
  }

  &--finishBlock {
    width: 100%;
    height: 6rem;
    margin-bottom: 0.5rem;
  }

  &--adapt {

    &-row {
      display: flex;
      min-width: 420px;
    }

  }
}
</style>
