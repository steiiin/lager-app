<script setup>

/**
 * LcBookManuallyDialog - Dialog component
 *
 * A dialog to select an amount by size for a given item.
 * Additionally it shows location information.
 *
 * Props (via "open"):
 *  - item (Object): The desired item object.
 *
 * Returns (via promise):
 *  - Amount (Number) if something was chosen.
 *  - Null if canceled.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, onMounted, onUnmounted } from 'vue'

  // Local composables
  import InputService from '@/Services/InputService'

// #endregion

// #region Dialog-Logic

  // DialogProps
  const isVisible = ref(false)
  let resolvePromise

  // DialogMethods
  const open = async (opts) => {

    selectedItem.value = opts.item
    currentSize.value = opts.item.basesize
    currentAmount.value = 1

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

    const handleEnter = async (e) => {
      if (!isVisible.value) { return }
      e.canceled = true
      accept()
    }
    const handleUp = () => {
      if (!isVisible.value) { return }
      let index = selectedItem.value.sizes.findIndex(e => e.amount === currentSize.value.amount) ?? 0
      if (index > 0) {
        currentSize.value = selectedItem.value.sizes[index - 1];
      }
    }
    const handleDown = () => {
      if (!isVisible.value) { return }
      let index = selectedItem.value.sizes.findIndex(e => e.amount === currentSize.value.amount) ?? 0
      if (index < (selectedItem.value.sizes.length - 1)) {
        currentSize.value = selectedItem.value.sizes[index + 1];
      }
    }
    const handleLeft = () => {
      if (!isVisible.value) { return }
      if (currentAmount.value > currentMin.value) {
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

  const selectedItem = ref(null)
  const currentSize = ref(null)
  const currentAmount = ref(0)

  // #region SizeProps

    const sizesList = computed(() => !selectedItem.value ? [] : selectedItem.value.sizes)
    const currentBaseSize = computed(() => !selectedItem.value ? null : selectedItem.value.basesize.unit)

    const currentCalculation = computed(() => isValidAmount ? Math.round(currentAmount.value * currentSize.value.amount) : 0)

  // #endregion
  // #region TemplateProps

    const isValidAmount = computed(() => currentAmount.value > 0)
    const currentMin = computed(() => (currentSize.value.amount%2 === 0) ? 0.5 : 1)

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
  <v-dialog v-model="isVisible" max-width="850px" @after-leave="cancel">
    <v-card prepend-icon="mdi-basket" :title="selectedItem.name" class="rounded-0">
      <v-divider></v-divider>
      <v-card-text class="lc-dialog-bookmanually">

        <v-form>
          <v-row>
            <v-col cols="6">

              <v-chip label
                class="mb-2">Hier findest du es:
              </v-chip>

              <div class="lc-dialog-bookmanually__location">
                <div class="lc-dialog-bookmanually__location-coarse">
                  <v-icon icon="mdi-domain"></v-icon>
                  {{ selectedItem.location.room }}
                </div>
                <div class="lc-dialog-bookmanually__location-cab mt-2" v-if="selectedItem.location.cab">
                  <v-icon icon="mdi-fridge"></v-icon>
                  {{ selectedItem.location.cab }}
                </div>
                <div class="lc-dialog-bookmanually__location-exact mt-2" v-if="selectedItem.location.exact">
                  <v-icon icon="mdi-archive-marker-outline"></v-icon>
                  {{ selectedItem.location.exact }}
                </div>
              </div>

              <v-divider class="my-3"></v-divider>

              <div class="lc-dialog-bookmanually__shortcutshint">
                <div class="row">
                  <div class="keys"><kbd sym>&larr;</kbd>/<kbd sym>&rarr;</kbd></div>
                  <div class="text">Menge ändern</div>
                </div>
                <div class="row" v-show="sizesList.length > 1">
                  <div class="keys"><kbd sym>&uarr;</kbd>/<kbd sym>&darr;</kbd></div>
                  <div class="text">Größe ändern</div>
                </div>
              </div>

            </v-col>
            <v-divider vertical></v-divider>
            <v-col cols="6">

              <v-chip color="primary" label
                class="mb-2">Gib die Menge an, die du ausbuchen möchtest:
              </v-chip>

              <v-number-input v-model="currentAmount"
                :min="currentMin"
                :reverse="false" controlVariant="split"
                :hideInput="false" :inset="false" hide-details>
              </v-number-input>

              <v-select v-model="currentSize"
                :items="sizesList"
                label="Größen" item-title="unit"
                return-object required hide-details>
              </v-select>

              <v-alert v-if="showCalculation" :text="currentCalculation + ' ' + currentBaseSize" class="mt-4"></v-alert>

            </v-col>
          </v-row>
        </v-form>

      </v-card-text>
      <v-divider></v-divider>
      <v-card-actions class="mx-4 mb-2">
        <v-btn
          @click="cancel">Abbrechen
        </v-btn>
        <v-btn
          color="primary" variant="tonal" :disabled="!isValidAmount"
          @click="accept">Übernehmen
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<style lang="scss">
.lc-dialog-bookmanually {

  padding: calc(16px + .5rem) !important;

  &__location {

    &-coarse {
      font-size: 1.2rem;
      display: flex;
      gap: 0.5rem;
    }
    &-cab,
    &-exact {
      font-size: 1.0rem;
      display: flex;
      gap: 0.5rem;
      margin-left: 1rem;
    }
    &-exact {
      margin-left: 2rem;
    }

  }

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