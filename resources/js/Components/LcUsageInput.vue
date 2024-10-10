<script setup>

// #region imports

  // Vue composables
  import { computed, onMounted, onUnmounted } from 'vue'

  // Local composables
  import { useInventoryStore } from '@/Services/StoreService'

  // Local components
  import LcButton from '@/Components/LcButton.vue'
  import LcScanIndicator from '@/Components/LcScanIndicator.vue'
  import InputService from '@/Services/InputService'

// #endregion

// #region props/emits

  const inventoryStore = useInventoryStore()

  const emit = defineEmits([
    'selectUsage',
  ])
  const props = defineProps({
    isUnlocked: {
      type: Boolean,
      default: false,
    },
  })

// #endregion

// #region selection

  // computed
  const hasAnyUsages = computed(() => inventoryStore.usages.length > 0)

  // methods
  const selectUsage = (usage) => {
    emit('selectUsage', usage)
  }
  const findUsage = (code) => {
    const found = inventoryStore.usages.find(u => u.barcode === code)
    if (!!found) { selectUsage(found) }
  }

// #endregion

// #region mount/unmount

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

      <div class="lc-picker--scanner">
        <LcScanIndicator 
          :active="hasAnyUsages">
        </LcScanIndicator>
      </div>
      <div class="lc-picker--description">
        <div class="lc-picker--description_title">
          {{ 
            hasAnyUsages
            ? 'Scanne oder wähle ein Fahrzeug ...'
            : 'Keine Verwendungen angelegt'  
          }}
        </div>
      </div>

    </div>
    <div class="lc-pickerresult" v-if="hasAnyUsages">

      <template v-for="usage in inventoryStore.usages">
        <LcButton v-if="!usage.is_locked || isUnlocked"
          class="lc-pickerresult-usage" 
          @click="selectUsage(usage)">{{ usage.name }}
        </LcButton>
      </template>

    </div>

</template>
<style lang="scss" scoped>
.lc-picker {

  display: flex;
  gap: .5rem;

  &--scanner,
  &--btn {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 6rem;
    height: 6rem;
  }
  &--scanner {
    background: var(--lc-secondary-accent-background);
  }

  &--description {
    flex: 1;
    border: .5rem solid var(--lc-secondary-accent-background);
    background: var(--lc-secondary-accent-background);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: end;

    & > * {
      opacity: .3;
    }
    &_title {
      font-weight: bold;
      font-size: 1.3rem;
    }
  }

}
.lc-pickerresult {

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
</style>
