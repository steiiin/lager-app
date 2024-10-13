// composables/useBaseSize.js

import { computed, toValue } from 'vue'

/**
 * useBaseSize
 *
 * Computes the base size and its unit from the sizes array.
 *
 * @param {Array} sizes - Array of size objects with 'amount' and 'unit'.
 * @returns {Object} - Contains 'baseSize' and 'baseUnit'.
 */
export function useBaseSize(sizes) {
  const baseSize = computed(() => toValue(sizes).find(size => size.amount === 1) ?? null)
  const baseUnit = computed(() => !toValue(baseSize) ? null : baseSize.value.unit)
  return { baseSize, baseUnit }
}
