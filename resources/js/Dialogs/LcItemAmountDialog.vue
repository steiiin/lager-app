<script setup>

/**
 * LcItemAmountDialog - Dialog component
 *
 * A dialog to select an amount by size for a given item.
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
  import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

  // Vuetify components
  import { VNumberInput } from 'vuetify/labs/VNumberInput'

  // Local composables
  import { useBaseSize } from '@/Composables/useBaseSize'
  import InputService from '@/Services/InputService'

// #endregion

// #region Dialog-Logic

  // DialogProps
  const isVisible = ref(false)
  const dialogTitle = ref('')
  const dialogMessage = ref('')
  let resolvePromise = null

  // DialogMethods
  const open = async (opts) => {

    dialogTitle.value = opts.title
    dialogMessage.value = opts.message

    sizesList.value = opts.sizes
    currentSize.value = null

    await nextTick()

    currentSize.value = opts.selectedSize ?? baseSize.value
    currentAmount.value = opts.selectedAmount ?? 1

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

  // #region KeyboardShortcuts

    const handleEnter = () => {
      if (!isVisible.value) { return }
      accept()
    }
    const handleUp = () => {
      if (!isVisible.value) { return }
      let index = sizesList.value.findIndex(e => e.amount === currentSize.value.amount) ?? 0
      if (index > 0) {
        currentSize.value = sizesList.value[index - 1];
      }
    }
    const handleDown = () => {
      if (!isVisible.value) { return }
      let index = sizesList.value.findIndex(e => e.amount === currentSize.value.amount) ?? 0
      if (index < (sizesList.value.length - 1)) {
        currentSize.value = sizesList.value[index + 1];
      }
    }
    const handleLeft = () => {
      if (!isVisible.value) { return }
      if (currentAmount.value > 0) {
        currentAmount.value --
      }
    }
    const handleRight = () => {
      if (!isVisible.value) { return }
      currentAmount.value ++
    }

  // #endregion


// #endregion
// #region Calculation-Logic

  const currentSize = ref(null)
  const currentAmount = ref(0)

  // #region SizesProps

    const sizesList = ref([])
    const { baseSize, baseUnit } = useBaseSize(sizesList)

    const currentCalculation = computed(() => isValidAmount ? Math.round(currentAmount.value * currentSize.value.amount) : 0)
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
  // #region TemplateProps

    const isValidAmount = computed(() => currentAmount.value >= 0)
    const currentStep = computed(() => (currentAmount.value % 1 === 0.5) ? 0.5 : 1)

    const showCalculation = computed(() => isValidAmount.value && currentSize.value.amount>1)

  // #endregion

// #endregion

// #region Lifecycle

  onMounted(() => {
    InputService.registerEnter(handleEnter)
    InputService.registerUp(handleUp)
    InputService.registerDown(handleDown)
    InputService.registerLeft(handleLeft)
    InputService.registerRight(handleRight)
  })
  onUnmounted(() => {
    InputService.unregisterEnter(handleEnter)
    InputService.unregisterUp(handleUp)
    InputService.unregisterDown(handleDown)
    InputService.unregisterLeft(handleLeft)
    InputService.unregisterRight(handleRight)
  })

// #endregion
// #region Expose

  defineExpose({ open, isVisible })

// #endregion

</script>

<template>
  <v-dialog v-model="isVisible" max-width="450px" @after-leave="cancel">
    <v-card prepend-icon="mdi-calculator" :title="dialogTitle" class="rounded-0">
      <v-divider></v-divider>
      <v-card-text>

        <p class="mb-4">{{ dialogMessage }}</p>

        <v-number-input v-model="currentAmount"
          :min="0" :step="currentStep"
          :reverse="false" controlVariant="split"
          :hideInput="false" :inset="false" hide-details>
        </v-number-input>

        <v-select v-model="currentSize" :items="sizesList"
          label="Größen" item-title="unit"
          return-object required hide-details>
        </v-select>

        <v-alert v-if="showCalculation" title="Menge" :text="currentCalculation + ' ' + baseUnit" class="mt-4"></v-alert>

        <v-divider class="my-3"></v-divider>

        <div class="lc-dialog-itemamount__shortcutshint">
          <div class="row">
            <div class="keys"><kbd sym>&larr;</kbd>/<kbd sym>&rarr;</kbd></div>
            <div class="text">Menge ändern</div>
          </div>
          <div class="row" v-show="sizesList.length > 1">
            <div class="keys"><kbd sym>&uarr;</kbd>/<kbd sym>&darr;</kbd></div>
            <div class="text">Größe ändern</div>
          </div>
        </div>

      </v-card-text>
      <v-divider></v-divider>
      <v-card-actions class="mx-4 mb-2">
        <v-btn
          @click="cancel">Abbrechen
        </v-btn>
        <v-btn color="primary" variant="tonal" :disabled="!isValidAmount"
          @click="accept">Übernehmen
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<style lang="scss">
.lc-dialog-itemamount {

  &__shortcutshint {

    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 0.9em;

    & kbd {
      font-size: 1.2em;
      display: inline-flex;
      justify-content: center;
      padding: 0;
      width: 24px;
      height: 24px;
      line-height: 24px;
    }

    & .row {
      display: inline-flex;
    }

  }

}
</style>