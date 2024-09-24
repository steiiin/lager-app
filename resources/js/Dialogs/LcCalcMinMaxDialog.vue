<script setup>

// #region imports

  // Vue composables
  import { ref, computed, watch, nextTick } from 'vue'

  // Vuetify components
  import { VNumberInput } from 'vuetify/labs/VNumberInput'

  // Local composables
  import { useBaseSize } from '@/Composables/CalcSizes'

// #endregion

// #region dialog

  // props
  const isVisible = ref(false)
  const dialogTitle = ref('')
  const dialogMessage = ref('')
  let resolvePromise = null

  // methods
  const open = async (opts) => {

    dialogTitle.value = opts.title
    dialogMessage.value = opts.message

    sizesList.value = opts.sizes
    allowZero.value = opts.allowZero ?? false

    currentSize.value = null
    await nextTick()

    currentSize.value = opts.curSize ?? baseSize.value
    currentAmount.value = opts.curAmount ?? 1

    isVisible.value = true
    return new Promise((resolve) => {
      resolvePromise = resolve
    })

  }

  const cancel = () => {
    isVisible.value = false
    resolvePromise(null)
  }
  const accept = () => {
    isVisible.value = false
    resolvePromise(currentCalculation.value)
  }

// #endregion

// #region calculation-logic

  // props
  const sizesList = ref([])
  const allowZero = ref(false)

  const { baseSize, baseUnit } = useBaseSize(sizesList)

  const currentSize = ref(null)
  const currentAmount = ref(0)

  const isValidAmount = computed(() => currentAmount.value >= 0)

  const currentCalculation = computed(() => isValidAmount ? Math.round(currentAmount.value * currentSize.value.amount) : 0)
  const currentMin = computed(() => {
    if (allowZero.value) { return 0 }
    return (currentSize.value.amount%2 === 0) ? 0.5 : 1
  })

  watch(currentSize, (newSize, oldSize) => { 
    if (!oldSize || !newSize) { return }

    if (newSize.amount < oldSize.amount) {
      currentAmount.value = oldSize.amount * currentAmount.value
    } else {
      if ((currentAmount.value % newSize.amount) === 0) {
        currentAmount.value = currentAmount.value / newSize.amount
      } else if ((newSize.amount % 2 === 0) && ((currentAmount.value % newSize.amount) === (newSize.amount / 2))) {
        currentAmount.value = currentAmount.value / newSize.amount
      } else {
        if (currentAmount.value / newSize.amount >= 1) {
          currentAmount.value = Math.floor((currentAmount.value / newSize.amount) * 2) / 2
        } else {
          currentAmount.value = 1
        }
      }
    }
  })

// #endregion

// #region expose

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="isVisible" max-width="450px" @after-leave="cancel">
    <v-card prepend-icon="mdi-calculator" :title="dialogTitle" class="rounded-0">

      <v-divider></v-divider>

      <v-card-text>
        <p class="mb-4">{{ dialogMessage }}</p>

        <v-number-input
          v-model="currentAmount"
          :reverse="false"
          controlVariant="split"
          :hideInput="false"
          :inset="false" hide-details
          :min="currentMin"
        ></v-number-input>

        <v-select
          v-model="currentSize"
          :items="sizesList"
          label="Größen"
          item-title="unit"
          return-object
          required
          hide-details
        ></v-select>

        <v-alert v-if="isValidAmount && currentSize.amount>1" title="Menge" :text="currentCalculation + ' ' + baseUnit" class="mt-4"></v-alert>

      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="mx-4 mb-2">
        <v-btn @click="cancel">Abbrechen</v-btn>
        <v-btn color="primary"
          variant="tonal" :disabled="!isValidAmount"
          @click="accept">Übernehmen</v-btn>
      </v-card-actions>

    </v-card>
  </v-dialog>
</template>
