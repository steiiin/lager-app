<script setup>

/**
 * BookOut - Page component
 *
 * This page let the user selects an usage and then scan or searching items
 * if something was taken from inventory.
 * After confirmation of the "booking-list" it is sent to backend.
 *
 * Props (via url-parameter)
 *  - usageId (optional|Number): Preselected usage, to jump directly to item-picker.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local composables
  import { useInventoryStore } from '@/Services/StoreService'
  import InputService from '@/Services/InputService'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcItemInput from '@/Components/LcItemInput.vue'
  import LcUsageInput from '@/Components/LcUsageInput.vue'
  import LcButton from '@/Components/LcButton.vue'
  import LcBookManuallyDialog from '@/Dialogs/LcBookManuallyDialog.vue'
  import LcConfirm from '@/Dialogs/LcConfirm.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'
  import LcFeedback from '@/Components/LcFeedback.vue'
  import IdleCursor from '@/Components/IdleCursor.vue'

// #endregion
// #region Props

  const inventoryStore = useInventoryStore()

  const props = defineProps({
    usageId: {
      type: Number,
      default: null,
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

    if (hasItemsInCart.value) {
      const confirmed = await confirmDialog.value.open({
        title: 'Abbrechen?',
        message: 'Du hast bereits Material hinzugefügt! <br>Willst du die Entnahme <b>wirklich abbrechen?</b>',
      })
      if (!confirmed) { return }
    }
    router.get('/')

  }

  // #region Keyboard-Shortcuts

    const handleIdle = () => {
      if (hasUsage.value || !hasItemsInCart.value) { handleEscape() }
      else { handleEnter() }
    }

    const handleEscape = () => {
      const canExit = itemPicker?.value?.handleEscape() ?? true
      if (canExit && !manuallyDialog.value.isVisible) {
        openWelcome()
      }
    }

    const handleEnter = () => {
      const canExit = itemPicker?.value?.handleEnter() ?? false
      if (canExit && !manuallyDialog.value.isVisible) {
        finishBookOut()
      }
    }

  // #endregion

// #endregion

// #region Booking-Logic

  // #region UsagePicker

    // Props
    const hasUsage = computed(() => bookoutForm.usage_id !== null)

    // Methods
    const getUsageById = (usage_id) => {
      return inventoryStore.usages.find(u => u.id === usage_id) ?? null
    }
    const selectUsage = (usage) => {
      bookoutForm.usage_id = usage.id ?? null
    }
    const clearUsage = () => {
      bookoutForm.usage_id = null
    }
    const selectInternalBookUndo = () => {
      bookoutForm.usage_id = -2 // "Inv-Rückbuchung"
    }
    const selectInternalExpired = () => {
      bookoutForm.usage_id = -3 // "Inv-Verfall"
    }

    const getUsageName = () => {

      if (!bookoutForm.usage_id) { return '' }
      if (bookoutForm.usage_id == -2) { return 'Rückbuchung' }
      if (bookoutForm.usage_id == -3) { return 'Verfall' }
      return getUsageById(bookoutForm.usage_id)?.name ?? 'Interne Verwendung'

    }

    const UsageIcon = computed(() => {

      if (bookoutForm.usage_id == -2) { return "mdi-undo" }
      if (bookoutForm.usage_id == -3) { return "mdi-clock-alert" }
      return "mdi-truck"

    })

    const warnAboutUsage = () => {
      feedback.value.usageError()
    }

  // #endregion
  // #region ItemPicker

    const itemPicker = ref(null)

    // Props
    const hasItemsInCart = computed(() => bookoutForm.entries.length > 0)

    // #region Table-Props

      // DataSource
      const preparedCart = computed(() => {
        return bookoutForm.entries.map(e => {
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

      // TableConfig
      const tableHeader = ref([
        { title: 'Material', key: 'name', minWidth: '50%' },
        { title: 'Menge', key: 'amount', align: 'end', minWidth: '5%' },
        { title: '', key: 'action', align: 'center', minWidth: '3%', sortable: false },
      ])
      const tableSortBy = ref([
        { key: 'demand', order: 'asc' },
        { key: 'name', order: 'asc' },
      ])

    // #endregion

    // #region Select-Logic

      const manuallyDialog = ref(null)

      const selectItem = async (item, amount) => {

        if (amount === null) {

          // selected manually, show amount-dialog
          const manualAmount = await manuallyDialog.value.open({
            item: item
          })
          if (manualAmount !== null) {

            if (setAmountInCart(item, manualAmount) < manualAmount) { notifyMaxBook(item) }
            else { notifyScan(item) }

          }

        } else {

          // per scan
          if (setAmountInCart(item, amount, true) < amount) { notifyMaxBook(item) }
          else { notifyScan(item) }

        }

      }
      const reduceItem = async (row) => {

        const entry = bookoutForm.entries.find(e => e.item_id === row.item.id)
        if (entry.item_amount > 1) {
          entry.item_amount = entry.item_amount - 1
        } else {
          const entryIndex = bookoutForm.entries.indexOf(entry);
          if (entryIndex !== -1) {
            bookoutForm.entries.splice(entryIndex, 1);
          }
        }

      }

      const setAmountInCart = (item, amount, add = false) => {

        const max = item.max_bookin_quantity == 0 ? Infinity : item.max_bookin_quantity
        const entry = bookoutForm.entries.find(e => e.item_id === item.id)
        if (!entry) {

          // create entry
          amount = Math.min(amount, max)
          bookoutForm.entries.push({
            item_id: item.id,
            item_amount: amount
          })
          return amount

        } else {

          if ((entry.item_amount + amount) > max)
          {
            entry.item_amount = max
          }
          else
          {

            entry.item_amount = add
              ? (entry.item_amount + amount)
              : amount;

          }

          return entry.item_amount

        }

      }

      // #region Scan-Notification

        const feedback = ref(null)

        const notifyScan = (item) => {
          feedback.value.scanSuccess(item)
        }

        const notifyMaxBook = (item) => {
          feedback.value.error(item.name, `Du hast die Maximalmenge für diesen Artikel erreicht. Bitte nicht noch mehr ausbuchen.`)
        }

      // #endregion

    // #endregion

  // #endregion

  // #region Finish

    // Form
    const bookoutForm = useForm({
      usage_id: null,
      entries: [],
    })
    const bookoutFormOptions = {
      preserveScroll: true,
      onSuccess: () => {
      },
    }

    // Post
    const finishBookOut = () => {
      if (hasItemsInCart.value && hasUsage.value) {
        bookoutForm.post('/bookout', bookoutFormOptions)
      }
    }

  // #endregion

// #endregion

// #region Lifecycle

  onMounted(() => {

    // set usage, if set by props
    if (!!props.usageId) {
      bookoutForm.usage_id = props.usageId
    }

    // register input
    InputService.registerEsc(handleEscape)
    InputService.registerEnter(handleEnter)
    InputService.registerIdle(handleIdle)

    // load store
    inventoryStore.fetchStore()

  })
  onUnmounted(() => {
    InputService.unregisterEsc(handleEscape)
    InputService.unregisterEnter(handleEnter)
    InputService.unregisterIdle(handleIdle)
  })

// #endregion

</script>

<template>

  <Head title="Verbrauch" />
  <IdleCursor />

  <div class="page-bookout">

    <LcPagebar title="Verbrauch" :disabled="bookoutForm.processing" @back="openWelcome"></LcPagebar>

    <!-- UsagePicker -->
    <section>

      <LcUsageInput v-if="!hasUsage"
        @select-usage="selectUsage" @other-code="warnAboutUsage"
        @ctrl-finish="openWelcome"
        @ctrl-expired="selectInternalExpired">
      </LcUsageInput>

      <div v-else class="page-bookout__usage">

        <LcButton v-if="!bookoutForm.processing && !hasItemsInCart"
          class="page-bookout__usage-clear" prepend-icon="mdi-close"
          @click="clearUsage">
        </LcButton>

        <v-spacer></v-spacer>

        {{ getUsageName() }}
        <v-icon :icon="UsageIcon"></v-icon>

      </div>

    </section>

    <!-- ItemPicker -->
    <section v-if="hasUsage">

      <LcItemInput ref="itemPicker"
        :cart="bookoutForm.entries"
        :result-specs="{ w: 850, i: 19 }"
        :disabled="manuallyDialog?.isVisible || bookoutForm.processing"
        @select-item="selectItem"
        @ctrl-finish="finishBookOut"
        @ctrl-expired="selectInternalExpired">
      </LcItemInput>

    </section>

    <!-- Cart -->
    <section class="page-bookout__cart" v-if="hasUsage && hasItemsInCart">

      <LcButton
        class="page-bookout__finish" :loading="bookoutForm.processing"
        type="primary" prepend-icon="mdi-invoice-check"
        @click="finishBookOut">{{ bookoutForm.processing ? 'Entnahme buchen ...' : 'Fertig mit der Entnahme' }}
      </LcButton>

      <v-data-table v-if="!bookoutForm.processing"
        :items="preparedCart" :headers="tableHeader" :sort-by="tableSortBy"
        density="compact" :items-per-page="999"
        hide-default-footer class="page-bookout__cart-table">
        <template v-slot:item.action="{ item }">
          <v-btn variant="flat" class="page-bookout__cart-table--reduce"
            @click="reduceItem(item)">
            <v-icon icon="mdi-delete"></v-icon>
          </v-btn>
        </template>
      </v-data-table>

    </section>

    <!-- Dialogs -->
    <LcBookManuallyDialog ref="manuallyDialog" />
    <LcConfirm ref="confirmDialog" />
    <LcRouteOverlay v-show="isRouting" />
    <LcFeedback ref="feedback" />

  </div>

</template>
<style lang="scss" scoped>
.page-bookout {

  height: 100%;

  &__usage {
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

    &-clear {
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

  &__finish {
    width: 100%;
    height: 6rem;
    margin-bottom: 0.5rem;
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

  &__cart {

    height: calc(100% - 20rem);

    &-table {

      height: calc(100% - 6.5rem);
      overflow-y: auto;
      overflow-x: hidden;

      &--reduce {
        width: 4rem;
        height: 2rem;
        margin: 0 -22px 0 -12px;
        border-radius: 0;
        font-size: 1.2rem;
      }

    }

    &::-webkit-scrollbar {
      display: none;
    }

  }

}
</style>
