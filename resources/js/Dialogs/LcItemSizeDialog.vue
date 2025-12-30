<script setup>

/**
 * LcItemSizeDialog - Dialog component
 *
 * A dialog to edit an item size.
 *
 * Props (via "open"):
 *  - title (String): Title of the dialog.
 *  - message (String): A descriptiv message of the dialog.
 *  - sizes (Array): An array of item-sizes for selection.
 *  - selectedSize (optional|Object): The preselected size. Default is the basesize.
 *  - selectedAmount (optional|Number): The preselected amount. Default is 1.
 *
 * Returns (via promise):
 *  - Amount (Number) if something was chosen.
 *  - Null if canceled.
 *
 */


// #region Imports

  // Vue composables
  import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

  // Local composables
  import InputService from '@/Services/InputService'

// #endregion
// #region Props

  const props = defineProps({
    sizes: {
      type: Array,
      required: true,
    },
    visible: {
      type: Boolean,
      default: false,
    },
  })

  const emit = defineEmits(['update:visible'])

// #endregion

// #region Dialog-Logic

  const currentEditSizeItem = ref(null)
  const isDialogVisible = ref(false)

  watch(isDialogVisible, (v) => {
    emit('update:visible', v)
  })

  const isEditSizeNew = computed(() => !currentEditSizeItem.value?.origOneUnit)
  const isEditSizeUnitExisting = computed(() => {
    const cur = currentEditSizeItem.value
    if (!cur) return false

    return props.sizes.some((e) => {
      const eUnit = (e.unit ?? '').toLowerCase()
      const curUnit = (cur.unit ?? '').toLowerCase()
      const orig = (cur.origOneUnit ?? '').toLowerCase()
      return eUnit === curUnit && eUnit !== orig
    })
  })
  const isEditSizeAmountExisting = computed(() => {
    const cur = currentEditSizeItem.value
    if (!cur) return false

    return props.sizes.some((e) => e.amount === cur.amount && e.amount !== cur.origOneAmount)
  })
  const isEditSizeEmpty = computed(() => {
    const cur = currentEditSizeItem.value
    if (!cur) return true
    return !cur.unit || !cur.amount
  })
  const isEditSizeDefault = computed(() => currentEditSizeItem.value?.defaultOne ?? false)
  const isEditSizeTooLow = computed(() => {
    const cur = currentEditSizeItem.value
    if (!cur) return false
    return cur.amount < 2 && !cur.defaultOne
  })

  const isValidSizeEdit = computed(() => {
    return !isEditSizeEmpty.value && !isEditSizeUnitExisting.value && !isEditSizeAmountExisting.value
  })

// #endregion
// #region Dialog-Methods

  const create = async () => {
    const nextAmount =
      props.sizes.length > 0 ? Math.max(...props.sizes.map((o) => Number(o.amount) || 0)) + 1 : 2

    currentEditSizeItem.value = {
      id: null,
      item_id: null,
      unit: `Stk-${props.sizes.length + 1}`,
      amount: nextAmount,
      is_default: false,
      origOneUnit: '',
      origOneAmount: 0,
      defaultOne: false,
    }

    isDialogVisible.value = true

    await nextTick()
    document.getElementById('id-editsize-amount')?.select()
    document.getElementById('id-editsize-amount')?.focus()
  }

  const edit = (item) => {
    currentEditSizeItem.value = {
      id: item?.id ?? null,
      item_id: item?.item_id ?? null,
      unit: item?.unit ?? '',
      amount: item?.amount ?? 0,
      is_default: !!item?.is_default,
      origOneUnit: (' ' + String(item?.unit ?? '').toLowerCase()).slice(1),
      origOneAmount: item?.amount ?? 0,
      defaultOne: (item?.amount ?? 0) === 1,
    }
    isDialogVisible.value = true
  }

  defineExpose({
    create,
    edit,
  })


  const cancel = () => {
    currentEditSizeItem.value = null
    isDialogVisible.value = false
  }

  const deleteSize = () => {
    const cur = currentEditSizeItem.value
    if (!cur) return

    const idx = props.sizes.findIndex((e) => e.unit === cur.unit)
    if (idx !== -1) {
      const deleted = props.sizes.splice(idx, 1)[0]
    }

    cancel()
  }

  const accept = () => {
    const cur = currentEditSizeItem.value
    if (!cur) return

    if (isEditSizeNew.value) {
      // append new size
      if (cur.is_default) {
        props.sizes.forEach((s) => (s.is_default = false))
      }
      props.sizes.push({
        id: cur.id,
        item_id: cur.item_id,
        unit: cur.unit,
        amount: cur.amount,
        is_default: cur.is_default,
      })
    } else {
      // edit size
      const existing = props.sizes.find((e) => e.amount === cur.origOneAmount)
      if (existing) {
        if (cur.is_default) {
          props.sizes.forEach((s) => (s.is_default = false))
        }
        existing.unit = cur.unit
        existing.amount = cur.amount
        existing.is_default = cur.is_default

        // keep at least one default if amount===1 and none is set
        if (
          existing.amount === 1 &&
          !existing.is_default &&
          !props.sizes.some((s) => s.is_default)
        ) {
          existing.is_default = true
        }
      }
    }

    cancel()
  }

// #endregion

// #region Lifecycle

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

// #endregion

</script>
<template>
  <v-dialog v-model="isDialogVisible" max-width="420px">
    <v-card
      v-if="currentEditSizeItem"
      class="rounded-0"
      prepend-icon="mdi-package-variant-closed"
      :title="isEditSizeNew ? 'Neue Packungsgröße' : 'Packungsgröße bearbeiten'"
    >
      <v-divider />

      <v-card-text>
        <p class="mb-4">Gib eine Packungsgröße an.</p>

        <v-text-field
          v-model.number="currentEditSizeItem.amount"
          :disabled="currentEditSizeItem.defaultOne"
          label="Menge"
          type="number"
          :min="2"
          hide-details
          id="id-editsize-amount"
        />

        <v-text-field
          v-model="currentEditSizeItem.unit"
          class="mt-2"
          id="id-editsize-unit"
          label="Größenangabe"
          hide-details
        />

        <v-checkbox
          v-model="currentEditSizeItem.is_default"
          label="In dieser Packungseinheit bestellen"
          hide-details
        />

        <v-alert
          class="mt-2"
          v-if="isEditSizeUnitExisting"
          text="Diese Einheit existiert bereits."
          type="error"
        />
        <v-alert
          class="mt-2"
          v-else-if="isEditSizeAmountExisting"
          text="Diese Menge exisitert bereits."
          type="error"
        />
        <v-alert
          class="mt-2"
          v-else-if="isEditSizeEmpty"
          text="Du musst alles ausfüllen."
          type="error"
        />
        <v-alert
          class="mt-2"
          v-else-if="isEditSizeTooLow"
          text="Du kannst nicht unter die Standardgröße."
          type="error"
        />
      </v-card-text>

      <v-divider />

      <v-card-actions class="mx-4 mb-2">
        <v-btn
          color="error"
          variant="tonal"
          v-if="!isEditSizeNew && !isEditSizeDefault"
          @click="deleteSize"
        >
          Delete
        </v-btn>

        <v-spacer />

        <v-btn @click="cancel">Abbrechen</v-btn>

        <v-btn
          color="primary"
          variant="tonal"
          :disabled="!isValidSizeEdit"
          @click="accept"
        >
          Speichern
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
