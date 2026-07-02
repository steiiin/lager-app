<script setup>

  import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

  import InputService from '@/Services/InputService'

  const props = defineProps({
    usages: {
      type: Array,
      required: true,
    },
    visible: {
      type: Boolean,
      default: false,
    },
  })

  const emit = defineEmits(['update:visible', 'save', 'delete'])

  const currentEditExpiryItem = ref(null)
  const currentExpiryMonth = ref(null)
  const currentExpiryYear = ref(null)
  const isDialogVisible = ref(false)

  watch(isDialogVisible, (v) => {
    emit('update:visible', v)
  })

  const usageOptions = computed(() => [
    { id: null, name: 'Lagerbestand' },
    ...props.usages.filter(e=>e.could_expire),
  ])

  const isEditExpiryNew = computed(() => !currentEditExpiryItem.value?.id && !currentEditExpiryItem.value?.client_id)
  const isStockExpiry = computed(() => currentEditExpiryItem.value?.usage_id === null)
  const isEditExpiryEmpty = computed(() => {
    const cur = currentEditExpiryItem.value
    if (!cur) return true
    return !cur.expiryAt || (!isStockExpiry.value && !cur.expiryQuantity)
  })
  const isValidExpiryEdit = computed(() => {
    const cur = currentEditExpiryItem.value
    return !!cur && !isEditExpiryEmpty.value && (isStockExpiry.value || cur.expiryQuantity > 0)
  })
  const currentUsageName = computed(() => {
    const cur = currentEditExpiryItem.value
    if (!cur) return ''

    return usageOptions.value.find(usage => usage.id === cur.usage_id)?.name ?? 'Lagerbestand'
  })
  const dialogTitle = computed(() => `Verfall (${currentUsageName.value}) bearbeiten`)

  const updateExpiry = () => {
    const cur = currentEditExpiryItem.value
    if (!cur) return

    if (!currentExpiryMonth.value || !currentExpiryYear.value) {
      cur.expiryAt = null
      return
    }

    cur.expiryAt = new Date(currentExpiryYear.value, currentExpiryMonth.value, 0)
  }

  watch(currentExpiryMonth, updateExpiry)
  watch(currentExpiryYear, updateExpiry)

  const create = async (itemId, usageId = null) => {
    const now = new Date()
    currentExpiryMonth.value = now.getMonth() + 1
    currentExpiryYear.value = now.getFullYear()
    currentEditExpiryItem.value = {
      id: null,
      item_id: itemId,
      usage_id: usageId,
      expiryAt: null,
      expiryQuantity: 1,
      status: 'reserved',
      is_ordered: false,
      note: '',
    }
    updateExpiry()

    isDialogVisible.value = true

    await nextTick()
    if (!isStockExpiry.value) {
      document.getElementById('id-editexpiry-quantity')?.select()
      document.getElementById('id-editexpiry-quantity')?.focus()
    }
  }

  const edit = (item) => {
    const expiryAt = item?.expiryAt ? new Date(item.expiryAt) : new Date()
    currentExpiryMonth.value = expiryAt.getMonth() + 1
    currentExpiryYear.value = expiryAt.getFullYear()

    currentEditExpiryItem.value = {
      id: item?.id ?? null,
      client_id: item?.client_id ?? null,
      item_id: item?.item_id ?? null,
      usage_id: item?.usage_id ?? null,
      expiryAt,
      expiryQuantity: item?.expiryQuantity ?? 1,
      status: item?.status ?? 'reserved',
      is_ordered: item?.is_ordered ?? false,
      note: item?.note ?? '',
    }
    isDialogVisible.value = true
  }

  defineExpose({
    create,
    edit,
  })

  const cancel = () => {
    currentEditExpiryItem.value = null
    isDialogVisible.value = false
  }

  const deleteExpiry = () => {
    const cur = currentEditExpiryItem.value
    if (!cur) return
    emit('delete', cur)
    cancel()
  }

  const accept = () => {
    const cur = currentEditExpiryItem.value
    if (!cur || !isValidExpiryEdit.value) return

    emit('save', {
      id: cur.id,
      client_id: cur.client_id,
      item_id: cur.item_id,
      usage_id: cur.usage_id,
      expiryAt: cur.expiryAt,
      expiryQuantity: isStockExpiry.value ? 1 : cur.expiryQuantity,
      status: cur.status ?? 'reserved',
      is_ordered: cur.is_ordered ?? false,
      note: cur.note,
    })
    cancel()
  }

  const handleEnter = () => {
    if (!isDialogVisible.value) { return }
    accept()
  }

  onMounted(() => {
    InputService.registerEnter(handleEnter)
  })
  onUnmounted(() => {
    InputService.unregisterEnter(handleEnter)
  })

</script>
<template>
  <v-dialog v-model="isDialogVisible" max-width="480px">
    <v-card
      v-if="currentEditExpiryItem"
      class="rounded-0"
      prepend-icon="mdi-calendar-clock"
      :title="dialogTitle"
    >
      <v-divider />

      <v-card-text>
        <v-row>
          <v-col cols="6">
            <v-number-input
              v-model="currentExpiryMonth"
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
              v-model="currentExpiryYear"
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

        <v-number-input
          v-if="!isStockExpiry"
          v-model="currentEditExpiryItem.expiryQuantity"
          class="mt-2"
          id="id-editexpiry-quantity"
          label="Menge"
          controlVariant="split"
          :hideInput="false"
          :inset="false"
          :min="1"
          :max="99999"
          hide-details
        />

        <v-textarea
          v-model="currentEditExpiryItem.note"
          class="mt-2"
          label="Notizen"
          rows="2"
          hide-details
        />

        <v-switch
          v-model="currentEditExpiryItem.is_ordered"
          class="mt-2"
          color="primary"
          label="Bestellt"
          hide-details
        />

        <v-alert
          class="mt-2"
          v-if="isEditExpiryEmpty"
          :text="isStockExpiry ? 'Du musst den Verfall ausfüllen.' : 'Du musst Verfall und Menge ausfüllen.'"
          type="error"
        />
      </v-card-text>

      <v-divider />

      <v-card-actions class="mx-4 mb-2">
        <v-btn
          color="error"
          variant="tonal"
          v-if="!isEditExpiryNew"
          @click="deleteExpiry"
        >
          Delete
        </v-btn>

        <v-spacer />

        <v-btn @click="cancel">Abbrechen</v-btn>

        <v-btn
          color="primary"
          variant="tonal"
          :disabled="!isValidExpiryEdit"
          @click="accept"
        >
          Speichern
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
