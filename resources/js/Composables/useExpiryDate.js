import { computed, ref, watch } from 'vue'
import { parseExpiryDate } from '../Utils/expiryDateUtils.js'

export function useExpiryDate() {
  const month = ref(null)
  const year = ref(null)
  const explicitDay = ref(null)
  const daysInMonth = computed(() => {
    if (!Number.isInteger(month.value) || month.value < 1 || month.value > 12
      || !Number.isInteger(year.value) || year.value < 1000 || year.value > 9999) return null
    return new Date(year.value, month.value, 0).getDate()
  })
  const day = computed({
    get: () => explicitDay.value ?? daysInMonth.value,
    set: value => { explicitDay.value = value === '' ? null : value },
  })

  // Flush synchronously so reset() and Enter never observe a pending month change.
  watch([month, year], () => {
    explicitDay.value = null
  }, { flush: 'sync' })

  const date = computed(() => {
    if (!daysInMonth.value || !Number.isInteger(day.value)
      || day.value < 1 || day.value > daysInMonth.value) return null
    return new Date(year.value, month.value - 1, day.value)
  })

  const reset = (value = null) => {
    const savedDate = parseExpiryDate(value)
    const initialDate = savedDate ?? new Date()
    explicitDay.value = null
    year.value = initialDate.getFullYear()
    month.value = initialDate.getMonth() + 1
    if (savedDate && savedDate.getDate() !== daysInMonth.value) {
      explicitDay.value = savedDate.getDate()
    }
  }

  return { month, year, day, daysInMonth, date, reset }
}
