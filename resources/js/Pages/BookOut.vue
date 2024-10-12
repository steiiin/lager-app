<script setup>

// #region imports

  // Vue composables
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local composables
  import { useInventoryStore } from '@/Services/StoreService'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcItemInput from '@/Components/LcItemInput.vue'
  import LcUsageInput from '@/Components/LcUsageInput.vue'
  import LcButton from '@/Components/LcButton.vue'
  import LcCalcAmountDialog from '@/Dialogs/LcCalcAmountDialog.vue'
  import LcConfirm from '@/Dialogs/LcConfirm.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'
  import IdleCursor from '@/Components/IdleCursor.vue'
  import InputService from '@/Services/InputService'

// #endregion

// #region props

  const inventoryStore = useInventoryStore()

  const props = defineProps({
    isUnlocked: {
      type: Boolean,
      default: false,
    },
    usageId: {
      type: Number,
      default: null,
    },
  })

// #endregion

// #region navigation

  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  const confirmDialog = ref(null)

  async function openWelcome() {

    if (hasAnyBookings.value) {
      const confirmed = await confirmDialog.value.open({
        title: 'Abbrechen?',
        message: 'Du hast bereits Material hinzugefügt! <br>Willst du die Entnahme <b>wirklich abbrechen?</b>',
      })
      if (!confirmed) { return }
    }
    router.get('/')

  }

// #endregion

// #region booking

  // #region form-data

    const bookingsForm = useForm({
      usage_id: null,
      entries: [],
    })
    const bookingsFormOptions = {
      preserveScroll: true,
      onSuccess: () => {
      },
    }
    const sendBooking = () => {
      if (hasAnyBookings.value && hasUsage.value)
      bookingsForm.post('/bookout', bookingsFormOptions)
    }

  // #endregion

  // #region usage

    const hasUsage = computed(() => bookingsForm.usage_id !== null)
    const getUsage = (usage_id) => {
      return inventoryStore.usages.find(u => u.id === usage_id)
    }

    const selectUsage = (usage) => {
      bookingsForm.usage_id = usage.id
    }
    const clearUsage = () => {
      bookingsForm.usage_id = null
    }

  // #endregion

  // #region item

    // computed
    const hasAnyBookings = computed(() => bookingsForm.entries.length > 0)
    const preparedBooking = computed(() => {
      return  bookingsForm.entries.map(e => {
        let item = inventoryStore.items.find(i => i.id === e.item_id)
        if (!item) { return null; }

        return {
          item: item,
          name: item.name,
          demand: item.demand.name,
          amount: `${e.item_amount} ${item.basesize.unit}`,
        }
      }).filter(e => e)
    })

    // refs
    const itemInput = ref(null)
    const amountCalc = ref(null)

    // methods
    const selectItem = async (item, amount) => {
      
      if (amount === null) {
        
        // selected manually, show amount-dialog
        const manualAmount = await amountCalc.value.open({
          item: item
        })
        if (manualAmount !== null) {
          setBookingAmount(item, manualAmount)
        }

      } else {

        // per scan
        setBookingAmount(item, amount, true)
        notifyScan(item)

      }

    }
    const reduceItem = async (row) => {

      const entry = bookingsForm.entries.find(e => e.item_id === row.item.id)
      if (entry.item_amount > 1) {
        entry.item_amount = entry.item_amount - 1
      } else {
        const entryIndex = bookingsForm.entries.indexOf(entry);
        if (entryIndex !== -1) {
          bookingsForm.entries.splice(entryIndex, 1);
        }
      }

    }

    const setBookingAmount = (item, amount, add = false) => {

      const entry = bookingsForm.entries.find(e => e.item_id === item.id)
      if (!entry) {

        // create entry
        bookingsForm.entries.push({ item_id: item.id, item_amount: amount })

      } else {

        // set entry
        entry.item_amount = add 
          ? (entry.item_amount + amount)
          : amount;

      }

    }

    // #region data-table

      const bookingsHeaders = ref([
        { title: 'Material', key: 'name', minWidth: '50%' },
        { title: 'Menge', key: 'amount', align: 'end', minWidth: '5%' },
        { title: '', key: 'action', align: 'center', minWidth: '3%', sortable: false },
      ])

      const bookingsSortBy = ref([
        { key: 'demand', order: 'asc' },
        { key: 'name', order: 'asc' },
      ])

    // #endregion

    // #region scan-notification

      const isScanNotificationVisible = ref(false)
      const scanNotificationText = ref('')

      const notifyScan = (item) => {
        scanNotificationText.value = `${item.name} wurde hinzugefügt!`
        isScanNotificationVisible.value = true
      }

    // #endregion

  // #endregion

// #endregion

// #region touchmode

  const handleEscape = () => {
    const canExit = itemInput?.value?.handleEscape() ?? true
    if (canExit && !amountCalc.value.isVisible) {
      openWelcome()
    }
  }

  onMounted(() => {

    // set usage, if set by props
    if (!!props.usageId) {
      bookingsForm.usage_id = props.usageId
    }
    
    // register input
    InputService.registerEsc(handleEscape)

    // load store
    inventoryStore.fetchStore()

  })
  onUnmounted(() => {
    InputService.unregisterEsc(handleEscape)
  })

// #endregion

</script>

<template>

  <Head title="Verbrauch" />
  <IdleCursor />

  <div class="app-BookOut">

    <LcPagebar title="Verbrauch" :disabled="bookingsForm.processing" @back="openWelcome"></LcPagebar>

    <div class="app-BookOut--page app-BookOut--usagepane">

      <LcUsageInput v-if="!hasUsage" :is-unlocked="isUnlocked"
        @select-usage="selectUsage">
      </LcUsageInput>

      <div v-else class="app-BookOut--usageBlock">

        <LcButton v-if="!bookingsForm.processing && !hasAnyBookings"
          class="app-BookOut--usageBlock-clearBtn" only-icon="mdi-close" 
          @click="clearUsage">
        </LcButton>

        <v-spacer></v-spacer>

        {{ getUsage(bookingsForm.usage_id)?.name ?? 'Keine Ahnung' }}
        <v-icon icon="mdi-truck"></v-icon>

      </div>

    </div>

    <div class="app-BookOut--page app-BookOut--inputpane" v-if="hasUsage">

      <LcItemInput ref="itemInput"
        :cart="bookingsForm.entries"
        :result-specs="{ w: 850, i: 19 }"
        :disabled="amountCalc?.isVisible || bookingsForm.processing"
        @select-item="selectItem"
        @ctrl-finish="sendBooking">
      </LcItemInput>

    </div>

    <div class="app-BookOut--page app-BookOut--resultpane" v-if="hasUsage && hasAnyBookings">

      <LcButton v-if="hasAnyBookings"
        class="app-BookOut--finishBlock" :loading="bookingsForm.processing"
        type="primary" prepend-icon="mdi-invoice-check"
        @click="sendBooking">{{ bookingsForm.processing ? 'Entnahme buchen ...' : 'Fertig mit der Entnahme' }}
      </LcButton>

      <v-data-table v-if="!bookingsForm.processing"
        :items="preparedBooking" :headers="bookingsHeaders" :sort-by="bookingsSortBy"
        density="compact" :items-per-page="999"
        hide-default-footer class="app-BookOut--resultpane-table">
        <template v-slot:item.action="{ item }">
          <v-btn variant="flat" class="app-BookOut--reduceBlock" @click="reduceItem(item)">
            <v-icon icon="mdi-delete"></v-icon>
          </v-btn>
        </template>
      </v-data-table>

    </div>

    <!-- Dialogs -->
    <LcCalcAmountDialog ref="amountCalc" />
    <LcConfirm ref="confirmDialog" />
    <LcRouteOverlay v-show="isRouting" />


    <v-snackbar 
      v-model="isScanNotificationVisible" 
      color="green" :height="100" centered
      :timeout="2000"><div class="text-center">{{ scanNotificationText }}</div>
    </v-snackbar>

  </div>

</template>
<style lang="scss" scoped>
.app-BookOut {

  height: 100%;

  &--page {
    max-width: 850px;
    margin: 0.5rem auto;
  }

  &--usageBlock {
    height: 4rem;
    background: var(--accent-primary-background);
    color: var(--accent-primary-foreground);
    display: flex;
    flex-direction: row-reverse;
    align-items: center;
    gap: 0.5rem;
    padding-left: 1.5rem;
    font-size: 1.2rem;
    font-weight: bold;

    &-clearBtn {
      width: 10rem;
      height: 100%;
      outline: 0.5rem solid var(--main-light);
    }

    &-title {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-left: 2rem;
    }
  }

  &--finishBlock {
    width: 100%;
    height: 6rem;
    margin-bottom: 0.5rem;
  }

  &--reduceBlock {
    width: 4rem;
    height: 2rem;
    margin: 0 -22px 0 -12px;
    border-radius: 0;
    font-size: 1.2rem;
  }

  & :deep(.v-data-table__th) {
    background: var(--main-dark);
    color: var(--main-light) !important;
    border-bottom: solid var(--main-light);
    border-left: 0.5rem solid white;
    font-weight: bold;
    text-transform: uppercase;

    &:first-child {
      border-left: none;
    }
  }

  &--resultpane {

    height: calc(100% - 20rem);
    &-table {
      height: calc(100% - 6.5rem);
      overflow-y: auto;
      overflow-x: hidden;
    }

    &::-webkit-scrollbar {
      display: none;
    }

  }

}
</style>
