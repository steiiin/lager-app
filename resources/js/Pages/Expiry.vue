<script setup>

/**
 * Expiry - Page component
 *
 * Shows material expiry grouped by usage and expiry date.
 *
 */

// #region Imports

  import { computed, ref, onMounted, onUnmounted } from 'vue'
  import { Head, router } from '@inertiajs/vue3'

  import InputService from '@/Services/InputService'

  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'
  import IdleCursor from '@/Components/IdleCursor.vue'

// #endregion
// #region Props

  const props = defineProps({
    expiryData: {
      type: Array,
      required: true,
    },
  })

// #endregion
// #region Navigation

  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  function openWelcome() {
    if (isDismissDialogVisible.value) {
      cancelDismiss()
      return
    }

    router.get('/')
  }

// #endregion
// #region Table

  const tableHeader = ref([
    { title: 'Material', key: 'item_name', width: '56%' },
    { title: 'Verfällt', key: 'amountLabel', align: 'end', width: '14%' },
    { title: 'Status', key: 'state_label', align: 'center', width: '20%' },
    { title: '', key: 'action', align: 'center', sortable: false, width: '10%' },
  ])
  const tableSortBy = ref([
    { key: 'item_name', order: 'asc' },
  ])
  const getItemNote = (item) => item.note?.trim() ?? ''
  const getStateLabel = (item) => {
    if (item.state === 'green') {
      return `${item.state_label} (${item.inventory_expiry_label})`
    }

    return item.state_label
  }

  const groupedExpiryData = computed(() => {
    return props.expiryData.map(usage => ({
      ...usage,
      dates: usage.dates.map(dateGroup => ({
        ...dateGroup,
        items: dateGroup.items.map(item => ({
          ...item,
          amountLabel: `${item.amount} ${item.unit}`,
          inventoryLabel: `${item.inventory_amount} ${item.unit}`,
        })),
      })),
    }))
  })

// #endregion
// #region Dismiss

  const isDismissDialogVisible = ref(false)
  const isDismissing = ref(false)
  const dismissError = ref('')
  const dismissItem = ref(null)
  const dismissMonth = ref(null)
  const dismissYear = ref(null)

  const isDismissValid = computed(() => {
    return !!dismissItem.value
      && !!dismissMonth.value
      && !!dismissYear.value
      && dismissMonth.value >= 1
      && dismissMonth.value <= 12
  })

  const dismissTitle = computed(() => {
    if (!dismissItem.value) { return 'Verfall erledigen' }
    return `${dismissItem.value.item_name} tauschen`
  })

  const toDateString = (date) => {
    const year = date.getFullYear()
    const month = (date.getMonth() + 1).toString().padStart(2, '0')
    const day = date.getDate().toString().padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  const openDismissDialog = (item, usageId = null) => {
    const now = new Date()
    dismissItem.value = {
      ...item,
      dismiss_usage_id: item.row_type === 'stock' ? usageId : item.usage_id,
    }
    dismissMonth.value = now.getMonth() + 1
    dismissYear.value = now.getFullYear()
    dismissError.value = ''
    isDismissDialogVisible.value = true
  }

  const cancelDismiss = () => {
    if (isDismissing.value) { return }
    isDismissDialogVisible.value = false
    dismissItem.value = null
    dismissError.value = ''
  }

  const dismissExpiry = async () => {
    if (!isDismissValid.value || isDismissing.value) { return }

    isDismissing.value = true
    dismissError.value = ''

    try {
      const nextExpiryAt = toDateString(new Date(dismissYear.value, dismissMonth.value, 0))
      const payload = { nextExpiryAt }

      if (dismissItem.value.row_type === 'stock' && dismissItem.value.dismiss_usage_id) {
        payload.usage_id = dismissItem.value.dismiss_usage_id
      }
      if (dismissItem.value.state === 'red') {
        payload.update_inventory = true
      }

      await axios.put(`/api/item-expiry/${dismissItem.value.expiry_entry_id}/dismiss`, payload)

      isDismissDialogVisible.value = false
      dismissItem.value = null
      router.reload({ only: ['expiryData'] })
    } catch (error) {
      dismissError.value = error?.response?.data?.message ?? 'Der Verfall konnte nicht erledigt werden.'
    } finally {
      isDismissing.value = false
    }
  }

// #endregion
// #region Lifecycle

  onMounted(() => {
    InputService.registerEsc(openWelcome)
    InputService.registerIdle(openWelcome)
  })
  onUnmounted(() => {
    InputService.unregisterEsc(openWelcome)
    InputService.unregisterIdle(openWelcome)
  })

// #endregion

</script>

<template>

  <Head title="Verfall" />
  <IdleCursor />

  <div class="page-expiry">

    <LcPagebar title="Verfall" @back="openWelcome"></LcPagebar>

    <main class="page-expiry__content">

      <v-alert
        v-if="groupedExpiryData.length === 0"
        type="info"
        variant="tonal"
        text="Kein Verfall erfasst"
      />

      <section
        v-for="usage in groupedExpiryData"
        :key="usage.usage_id"
        class="page-expiry__usage"
      >
        <header class="page-expiry__usage-title">
          <v-icon icon="mdi-truck"></v-icon>
          {{ usage.usage_name }}
        </header>

        <section
          v-for="dateGroup in usage.dates"
          :key="dateGroup.expiry_date"
          class="page-expiry__date"
        >
          <div class="page-expiry__date-title">
            <v-icon icon="mdi-timer-sand-complete"></v-icon>
            {{ dateGroup.expiry_label }}
          </div>

          <v-data-table
            :items="dateGroup.items"
            :headers="tableHeader"
            :sort-by="tableSortBy"
            density="compact"
            :items-per-page="999"
            hide-default-footer
            class="page-expiry__table"
          >
            <template v-slot:item="{ item }">
              <tr class="page-expiry__row" :class="{ 'page-expiry__row--with-note': getItemNote(item) }">
                <td>
                  <div class="page-expiry__item" :class="`page-expiry__item--${item.state}`">
                    {{ item.item_name }}
                  </div>
                </td>
                <td class="text-end">{{ item.amountLabel }}</td>
                <td
                  :class="['text-center', 'page-expiry__status-cell', `page-expiry__status-cell--${item.state}`]"
                >
                  <span class="page-expiry__state" :class="`page-expiry__state--${item.state}`">
                    {{ getStateLabel(item) }}
                  </span>
                </td>
                <td class="text-center">
                  <v-btn
                    icon="mdi-check"
                    size="small"
                    variant="text"
                    :disabled="isDismissing"
                    @click="openDismissDialog(item, usage.usage_id)"
                  ></v-btn>
                </td>
              </tr>
              <tr v-if="getItemNote(item)" class="page-expiry__note-row" :class="`page-expiry__note-row--${item.state}`">
                <td colspan="4">
                  <div class="page-expiry__note">
                    <v-icon icon="mdi-note-text-outline" size="small"></v-icon>
                    <span>{{ getItemNote(item) }}</span>
                  </div>
                </td>
              </tr>
            </template>
          </v-data-table>
        </section>
      </section>

    </main>

    <LcRouteOverlay v-show="isRouting" />

    <v-dialog v-model="isDismissDialogVisible" max-width="480px" persistent>
      <v-card
        v-if="dismissItem"
        class="rounded-0"
        prepend-icon="mdi-calendar-check"
        :title="dismissTitle"
      >
        <v-divider />

        <v-card-text>

          <p style="padding-bottom: .5rem;">
            Bitte gib den nächsten Verfall an:
          </p>

          <v-row>
            <v-col cols="6">
              <v-number-input
                v-model="dismissMonth"
                label="Monat"
                controlVariant="split"
                :hideInput="false"
                :inset="false"
                :min="1"
                :max="12"
                hide-details
              />
            </v-col>
            <v-col cols="6">
              <v-number-input
                v-model="dismissYear"
                label="Jahr"
                controlVariant="split"
                :hideInput="false"
                :inset="false"
                :min="(new Date()).getFullYear() - 1"
                :max="(new Date()).getFullYear() + 99"
                hide-details
              />
            </v-col>
          </v-row>

          <v-alert
            v-if="dismissError"
            class="mt-2"
            type="error"
            :text="dismissError"
          />
        </v-card-text>

        <v-divider />

        <v-card-actions class="mx-4 mb-2">
          <v-spacer />
          <v-btn
            :disabled="isDismissing"
            @click="cancelDismiss"
          >
            Abbrechen
          </v-btn>
          <v-btn
            color="primary"
            variant="tonal"
            :disabled="!isDismissValid"
            :loading="isDismissing"
            @click="dismissExpiry"
          >
            Speichern
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </div>

</template>
<style lang="scss" scoped>
.page-expiry {

  height: 100%;
  background: var(--main-light);
  color: var(--main-dark);

  &__content {
    height: calc(100% - 6rem);
    overflow-y: auto;
    padding: 1rem;
  }

  &__usage {
    margin-bottom: 1rem;
  }

  &__usage-title {
    min-height: 4rem;
    background: var(--accent-primary-background);
    color: var(--accent-primary-foreground);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0 1.5rem;
    font-size: 1.2rem;
    font-weight: bold;
  }

  &__date {
    outline: 0.5rem solid var(--main-light);
    background: white;
  }

  &__date-title {
    min-height: 3rem;
    background: var(--main-dark);
    color: var(--main-light);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0 1rem;
    font-weight: bold;
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

    // hide left border for the last column
    &:last-child {
      border-left: none;
    }
  }

  & :deep(.v-data-table__td) {
    &:last-child:not(.page-expiry__status-cell) {
      border-left: none !important;
    }
  }

  &__table {
    border-bottom: 0.5rem solid var(--main-light);

    :deep(table) {
      table-layout: fixed;
      width: 100%;
    }
  }

  &__row {
    background: white;

    &--with-note {
      background: #fbfbfb;

      td {
        border-bottom-color: transparent !important;
      }
    }
  }

  &__item {
    border-left: 0.5rem solid transparent;
    margin-left: -1rem;
    padding-left: 0.5rem;
    font-weight: bold;

    &--red {
      border-left-color: #d32f2f;
    }

    &--yellow {
      border-left-color: #f9a825;
    }

    &--green {
      border-left-color: #2e7d32;
    }
  }

  &__status-cell {
    color: var(--main-light);
    border-left: 0.5rem solid white;
    padding: 0.75rem 1rem;
    vertical-align: middle;
    border-right: none;
    border-bottom: none !important;

    &--red {
      background: #d32f2f;
    }

    &--yellow {
      background: #f9a825;
      color: var(--main-dark);
    }

    &--green {
      background: #2e7d32;
    }
  }

  &__state {
    display: inline-flex;
    justify-content: center;
    min-width: 7rem;
    padding: 0.2rem 0.6rem;
    color: white;
    font-weight: bold;
    text-transform: uppercase;

    &--red {
      background: #d32f2f;
    }

    &--yellow {
      background: #f9a825;
      color: var(--main-dark);
    }

    &--green {
      background: #2e7d32;
    }
  }

  &__note-row {
    background: #fbfbfb;

    td {
      padding: 0 0 0.6rem 1rem !important;
      border-bottom: 0.35rem solid var(--main-light) !important;
    }

    &--red .page-expiry__note {
      border-left-color: #d32f2f;
    }

    &--yellow .page-expiry__note {
      border-left-color: #f9a825;
    }

    &--green .page-expiry__note {
      border-left-color: #2e7d32;
    }
  }

  &__note {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-left: -1rem;
    padding: 0.45rem 0.75rem 0.45rem 0.5rem;
    border-left: 0.5rem solid transparent;
    background: #f1f3f4;
    color: #42515a;
    font-size: 0.9rem;
    line-height: 1.35;
  }

}
</style>
