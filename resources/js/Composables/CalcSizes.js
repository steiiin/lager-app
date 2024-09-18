// #region imports

  import { computed, ref, watch } from 'vue'

// #endregion

export function useBaseSize(sizes) {
  const baseSize = computed(() => sizes.value.find(size => size.amount === 1) ?? null)
  const baseUnit = computed(() => !baseSize.value ? null : baseSize.value.unit)
  return { baseSize, baseUnit }
}

export function useSizesCalc(sizes, amount) {
  const sortedSizes = computed(() => sizes.value.slice().sort((a, b) => b.amount - a.amount))
  
  const lcmUnit = ref(null)
  const lcmAmount = ref(0)
  const findOptimalSize = (valSizes, valAmount) => {
    
    // skip if empty
    if (valSizes.length === 0) { 
      lcmUnit.value = null
      lcmAmount.value = null
      return 
    }

    // set smallest as default
    lcmUnit.value = valSizes[valSizes.length-1].unit
    lcmAmount.value = valAmount
    if (valAmount === 0) { return }

    // search "least common multiple" (or 0.5)
    for (let size of valSizes) {
      const sizeAmount = size.amount
      const divResult = valAmount / sizeAmount
      if (Number.isInteger(divResult) || (divResult % 1 === 0.5)) {
        lcmUnit.value = size.unit
        lcmAmount.value = divResult
        break
      }
    }

  }
  
  watch(sortedSizes, (value) => { findOptimalSize(value, amount.value) })
  watch(amount, (value) => { findOptimalSize(sortedSizes.value, value) })

  const lcmText = computed(() => (!!lcmUnit.value) ? `${lcmAmount.value} ${lcmUnit.value}` : null)

  return { lcmUnit, lcmAmount, lcmText }
}