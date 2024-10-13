// composables/useOptimalSize.js
import { computed, toValue } from 'vue'
import { findOptimalSize } from '@/Utils/sizeUtils' // Adjust the import path accordingly

/**
 * useOptimalSize
 *
 * Calculates the optimal size based on the provided sizes array and amount.
 *
 * @param {Ref|Reactive} sizes - Reactive reference to the sizes array.
 * @param {Ref|Reactive} amount - Reactive reference to the amount value.
 * @returns {Object} - Contains 'unit', 'amount', and 'text' as computed properties.
 */
export function useOptimalSize(sizes, amount) {
  // Sort sizes descendingly by amount to prioritize larger sizes first
  const sortedSizes = computed(() => [...toValue(sizes)].sort((a, b) => b.amount - a.amount))

  // Compute the optimal size using the utility function
  const optimalSize = computed(() => findOptimalSize(sortedSizes.value, toValue(amount)))

  // Computed property for formatted text
  const formattedText = computed(() => {
    if (!optimalSize.value.unit || optimalSize.value.amount === null) return null
    return `${optimalSize.value.amount} ${optimalSize.value.unit}`
  })

  return {
    unit: computed(() => optimalSize.value.unit),
    amount: computed(() => optimalSize.value.amount),
    text: formattedText
  }
}
