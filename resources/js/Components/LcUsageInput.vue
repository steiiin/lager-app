<script setup>

/**
 * LcUsageInput - Component
 *
 * An picker componant for scanning/selecting usages.
 *
 * Emits:
 *  - selectUsage: Is emitted when the user selects/scans an usage.
 *  - ctrlExpired: Emitted when the user scanned the expired-code.
 *  - otherCode: Emitted when the scanner detected other scancodes, eg. item code.
 *
 */

// #region Imports

  // Vue composables
  import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

  // Local composables
  import InputService from '@/Services/InputService'
  import { useInventoryStore } from '@/Services/StoreService'

  // Local components
  import LcButton from '@/Components/LcButton.vue'
  import LcScanIndicator from '@/Components/LcScanIndicator.vue'

// #endregion
// #region Props

  const inventoryStore = useInventoryStore()

  // #region TemplateProps

    const hasAnyUsages = computed(() => inventoryStore.usages.length > 0)

  // #endregion

// #endregion
// #regin Emits

  const emit = defineEmits([
    'selectUsage',
    'ctrlFinish',
    'ctrlExpired',
    'otherCode',
  ])

// #endregion

// #region Picker-Logic

  const navigationIndex = ref(0)
  const isKeyboardNavigationActive = ref(false)

  const selectionOptions = computed(() => {
    if (!hasAnyUsages.value) { return [] }

    return [
      ...inventoryStore.usages.map(usage => ({
        type: 'usage',
        value: usage,
        key: `usage-${usage.barcode}`,
      })),
      { type: 'expired', value: null, key: 'expired' },
    ]
  })

  const selectUsage = (usage) => {
    emit('selectUsage', usage)
  }

  const handleExpiredSelection = () => {
    emit('ctrlExpired')
  }

  const findUsage = (params) => {

    const code = params.text

    // emit ctrl-codes
    if (code === 'LC-2000001000') {
      emit('ctrlFinish')
      return
    }
    if (code === 'LC-2000010000') {
      emit('ctrlExpired')
      return
    }

    // search usage
    const found = inventoryStore.usages.find(u => u.barcode === code)
    if (!!found) { selectUsage(found) }
    else { emit('otherCode') }

  }

  const ensureNavigationActive = () => {
    if (!selectionOptions.value.length) { return }
    if (!isKeyboardNavigationActive.value) {
      isKeyboardNavigationActive.value = true
      navigationIndex.value = 0
    }
  }

  const handleLeft = () => {
    if (!selectionOptions.value.length) { return }
    ensureNavigationActive()
    navigationIndex.value = (navigationIndex.value - 1 + selectionOptions.value.length) % selectionOptions.value.length
  }

  const handleRight = () => {
    if (!selectionOptions.value.length) { return }
    ensureNavigationActive()
    navigationIndex.value = (navigationIndex.value + 1) % selectionOptions.value.length
  }

  const handleEnter = () => {
    if (!isKeyboardNavigationActive.value || !selectionOptions.value.length) { return }

    const selection = selectionOptions.value[navigationIndex.value]
    if (selection.type === 'usage') { selectUsage(selection.value) }
    else { handleExpiredSelection() }
  }

  const handleSelection = (option) => {
    if (option.type === 'usage') { selectUsage(option.value) }
    else { handleExpiredSelection() }
  }

  watch(selectionOptions, (newOptions) => {
    if (!newOptions.length) {
      isKeyboardNavigationActive.value = false
      navigationIndex.value = 0
      return
    }

    if (navigationIndex.value >= newOptions.length) {
      navigationIndex.value = 0
    }
  })

// #endregion

// #region Lifecycle

  onMounted(() => {
    InputService.registerScan(findUsage)
    InputService.registerLeft(handleLeft)
    InputService.registerRight(handleRight)
    InputService.registerEnter(handleEnter)
  })

  onUnmounted(() => {
    InputService.unregisterScan(findUsage)
    InputService.unregisterLeft(handleLeft)
    InputService.unregisterRight(handleRight)
    InputService.unregisterEnter(handleEnter)
  })

// #endregion

</script>
<template>

    <div class="lc-picker">

      <div class="lc-picker__scanner">
        <LcScanIndicator
          :active="hasAnyUsages">
        </LcScanIndicator>
      </div>
      <div class="lc-picker__description">
        <div class="lc-picker__description-title">
          {{
            hasAnyUsages
            ? 'Scanne oder wähle ein Fahrzeug ...'
            : 'Keine Verwendungen angelegt'
          }}
        </div>
      </div>

    </div>
    <div class="lc-picker__result" v-if="hasAnyUsages">

      <template v-for="(option, index) in selectionOptions" :key="option.key">
        <LcButton
          class="lc-picker__result-usage"
          :class="{ 'lc-picker__result-usage--selected': isKeyboardNavigationActive && navigationIndex === index }"
          :selected="isKeyboardNavigationActive && navigationIndex === index"
          @click="handleSelection(option)">
          {{ option.type === 'usage' ? option.value.name : 'Verfall' }}
        </LcButton>
      </template>

    </div>

</template>
<style lang="scss" scoped>
.lc-picker {

  display: flex;
  gap: .5rem;

  &__scanner {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 6rem;
    height: 6rem;
  }
  &__scanner {
    background: var(--accent-secondary-background);
  }

  &__description {
    flex: 1;
    border: .5rem solid var(--accent-secondary-background);
    background: var(--accent-secondary-background);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: end;

    & > * {
      opacity: .3;
    }
    &-title {
      font-weight: bold;
      font-size: 1.3rem;
    }
  }

  &__result {

    margin-top: .5rem;
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: .5rem;
    justify-content: space-between;

    &-usage {

      height: 4rem;
      min-width: 6rem;
      flex: 1;

      &--selected {
        outline: 3px solid var(--main-dark);
        outline-offset: 4px;
      }

    }

  }

}
</style>
