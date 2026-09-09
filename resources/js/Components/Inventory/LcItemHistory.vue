<script setup>
  import { onUnmounted, ref, watch } from 'vue'
  import axios from 'axios'

  const ITEMS_PER_PAGE = 50

  const props = defineProps({
    itemId: {
      type: Number,
      required: true,
    },
    baseUnit: {
      type: String,
      default: '',
    },
  })

  const headers = [
    { title: 'Zeitpunkt', key: 'occurred_at', sortable: false, width: '11rem' },
    { title: 'Vorgang', key: 'type', sortable: false, width: '9rem' },
    { title: 'Menge', key: 'amount', sortable: false, align: 'end', width: '8rem' },
    { title: 'Verwendung/Prozess', key: 'context', sortable: false },
  ]

  const eventTypes = {
    bookout: { label: 'Entnahme', color: 'error' },
    order: { label: 'Bestellung', color: 'warning' },
    bookin: { label: 'Lieferung', color: 'success' },
  }

  const events = ref([])
  const currentPage = ref(1)
  const total = ref(0)
  const loading = ref(false)
  const errorMessage = ref('')

  let requestSequence = 0
  let abortController = null

  const resetHistory = () => {
    events.value = []
    currentPage.value = 1
    total.value = 0
    errorMessage.value = ''
  }

  const loadHistory = async (page = 1) => {
    if (!props.itemId) {
      resetHistory()
      return
    }

    const sequence = ++requestSequence
    abortController?.abort()
    abortController = new AbortController()
    loading.value = true
    errorMessage.value = ''

    try {
      const response = await axios.get(`/api/inventory/${props.itemId}/history`, {
        params: { page },
        signal: abortController.signal,
      })

      if (sequence !== requestSequence) { return }

      events.value = response.data.data ?? []
      currentPage.value = Number(response.data.current_page ?? page)
      total.value = Number(response.data.total ?? 0)
    } catch (error) {
      if (sequence !== requestSequence || axios.isCancel(error) || error?.code === 'ERR_CANCELED') { return }
      errorMessage.value = 'Der Verlauf konnte nicht geladen werden.'
    } finally {
      if (sequence === requestSequence) {
        loading.value = false
      }
    }
  }

  const getEventType = (type) => eventTypes[type] ?? { label: 'Unbekannt', color: 'default' }

  const formatOccurredAt = (event) => {
    if (!event?.occurred_at) { return '–' }

    if (event.occurred_at_precision === 'date') {
      const match = event.occurred_at.match(/^(\d{4})-(\d{2})-(\d{2})$/)
      if (!match) { return event.occurred_at }

      return new Intl.DateTimeFormat('de-DE', { dateStyle: 'short' })
        .format(new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])))
    }

    const date = new Date(event.occurred_at)
    if (Number.isNaN(date.getTime())) { return event.occurred_at }

    return new Intl.DateTimeFormat('de-DE', {
      dateStyle: 'short',
      timeStyle: 'short',
    }).format(date)
  }

  const formatAmount = (amount) => `${amount} ${props.baseUnit}`.trim()

  const historyRowProps = ({ item }) => {
    const event = item?.raw ?? item
    return { class: `item-history__row item-history__row--${event?.type ?? 'unknown'}` }
  }

  watch(
    () => props.itemId,
    () => {
      resetHistory()
      loadHistory(1)
    },
    { immediate: true },
  )

  onUnmounted(() => {
    requestSequence++
    abortController?.abort()
  })
</script>

<template>
  <section class="item-history">
    <h3 class="text-subtitle-1 font-weight-bold mb-2">Verlauf</h3>

    <v-alert
      v-if="errorMessage"
      class="mb-3"
      type="error"
      variant="tonal"
      :text="errorMessage"
    >
      <template #append>
        <v-btn variant="text" @click="loadHistory(currentPage)">Erneut versuchen</v-btn>
      </template>
    </v-alert>

    <v-data-table-server
      v-model:page="currentPage"
      class="item-history__table"
      density="compact"
      item-value="key"
      :headers="headers"
      :items="events"
      :items-length="total"
      :items-per-page="ITEMS_PER_PAGE"
      :items-per-page-options="[{ value: ITEMS_PER_PAGE, title: String(ITEMS_PER_PAGE) }]"
      :loading="loading"
      :row-props="historyRowProps"
      loading-text="Verlauf wird geladen …"
      no-data-text="Keine Einträge im Verlauf vorhanden"
      @update:page="loadHistory"
    >
      <template #item.occurred_at="{ item }">
        {{ formatOccurredAt(item) }}
      </template>

      <template #item.type="{ item }">
        <v-chip
          :color="getEventType(item.type).color"
          size="small"
          variant="tonal"
        >
          {{ getEventType(item.type).label }}
        </v-chip>
      </template>

      <template #item.amount="{ item }">
        <span class="font-weight-bold">{{ formatAmount(item.amount) }}</span>
      </template>

      <template #item.context="{ item }">
        <v-chip
          v-if="item.context"
          :color="item.context.kind === 'internal' ? 'black' : 'primary'"
          size="small"
          variant="outlined"
        >
          {{ item.context.label }}
        </v-chip>
        <span v-else>–</span>
      </template>
    </v-data-table-server>
  </section>
</template>

<style lang="scss" scoped>
  .item-history__table {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }

  :deep(.item-history__row--bookout > td) {
    background-color: rgba(var(--v-theme-error), .07);
  }

  :deep(.item-history__row--order > td) {
    background-color: rgba(var(--v-theme-warning), .08);
  }

  :deep(.item-history__row--bookin > td) {
    background-color: rgba(var(--v-theme-success), .07);
  }
</style>
