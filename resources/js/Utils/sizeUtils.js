// utils/sizeUtils.js

/**
 * findOptimalSize
 *
 * Determines the optimal size and unit based on the provided amount.
 *
 * @param {Array} sizes - Array of size objects sorted descendingly by 'amount'.
 * @param {number} amount - The amount to find the optimal size for.
 * @returns {Object|null} - Contains 'unit', 'amount', and 'text' if a suitable size is found; otherwise, null.
 */
export function findOptimalSize(sizes, amount) {
    if (!sizes || sizes.length === 0) {
      return null
    }

    const smallestSize = sizes[sizes.length - 1]
    let unit = smallestSize.unit
    let scaledAmount = amount

    if (amount !== 0) {
      for (let size of sizes) {
        const divResult = amount / size.amount
        if (Number.isInteger(divResult) || (divResult % 1 === 0.5)) {
          unit = size.unit
          scaledAmount = divResult
          break
        }
      }
    }

    return {
      unit,
      amount: scaledAmount,
      text: `${scaledAmount} ${unit}`
    }
  }
