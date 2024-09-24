// #region imports

  import { computed, ref, toValue, watch } from 'vue'

// #endregion

export function useBaseSize(sizes) {
  const baseSize = computed(() => toValue(sizes).find(size => size.amount === 1) ?? null)
  const baseUnit = computed(() => !toValue(baseSize) ? null : baseSize.value.unit)
  return { baseSize, baseUnit }
}


export function findOptimalSize(sizes, amount) {

  // skip if empty
  if (sizes.length === 0) { 
    return null 
  }

  // sort sizes
  const sortedSizes = sizes.slice().sort((a, b) => b.amount - a.amount)

  // init with basesize
  const result = { 
    unit: sortedSizes[sizes.length-1].unit,
    amount: amount
  }

  // set smallest as default
  if (amount === 0) { return result }

  // search "least common multiple" (or 0.5)
  for (let size of sortedSizes) {
    const sizeAmount = size.amount
    const divResult = amount / sizeAmount
    if (Number.isInteger(divResult) || (divResult % 1 === 0.5)) {
      result.unit = size.unit
      result.amount = divResult
      break
    }
  }

  result.text = `${result.amount} ${result.unit}`
  return result
  
}

export function useSizesCalc(sizes, amount) {
  
  const sortedSizes = computed(() => toValue(sizes).slice().sort((a, b) => b.amount - a.amount))
  
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
  findOptimalSize(toValue(sortedSizes), toValue(amount))

  const lcmText = computed(() => (!!lcmUnit.value) ? `${lcmAmount.value} ${lcmUnit.value}` : null)

  return { unit: lcmUnit, amount: lcmAmount, text: lcmText }
}