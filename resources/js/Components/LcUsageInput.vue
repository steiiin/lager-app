<script setup>

/**
 * LcUsageInput - Component
 *
 * An picker componant for scanning/selecting usages.
 *
 * Props:
 *  - isUnlocked (Boolean): If admin-usages are shown.
 *
 * Emits:
 *  - selectUsage: Is emitted when the user selects/scans an usage.
 *  - ctrlFinish: Emitted when the user scanned the finish-code.
 *  - ctrlExpired: Emitted when the user scanned the expired-code.
 *
 */

// #region Imports

  // Vue composables
  import { computed, onMounted, onUnmounted } from 'vue'

  // Local composables
  import InputService from '@/Services/InputService'
  import { useInventoryStore } from '@/Services/StoreService'

  // Local components
  import LcButton from '@/Components/LcButton.vue'
  import LcScanIndicator from '@/Components/LcScanIndicator.vue'

// #endregion
// #region Props

  const inventoryStore = useInventoryStore()

  const props = defineProps({
    isUnlocked: {
      type: Boolean,
      default: false,
    },
  })

  // #region TemplateProps

    const hasAnyUsages = computed(() => inventoryStore.usages.length > 0)

  // #endregion

// #endregion
// #regin Emits

  const emit = defineEmits([
    'selectUsage',
    'ctrlFinish',
    'ctrlExpired',
  ])

// #endregion

// #region Picker-Logic

  const selectUsage = (usage) => {
    emit('selectUsage', usage)
  }

  const findUsage = (code) => {

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

  }

// #endregion

// #region Lifecycle

  onMounted(() => {
    InputService.registerScan(findUsage)
  })

  onUnmounted(() => {
    InputService.unregisterScan(findUsage)
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

      <template v-for="usage in inventoryStore.usages">
        <LcButton v-if="!usage.is_locked || isUnlocked"
          class="lc-picker__result-usage"
          @click="selectUsage(usage)">{{ usage.name }}
        </LcButton>
      </template>

      <LcButton
        class="lc-picker__result-usage"
        @click="emit('ctrlExpired')">Verfall
      </LcButton>

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

    }

  }

}
</style>
