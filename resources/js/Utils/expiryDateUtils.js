// Expiry values are calendar dates, even when the API returns an ISO timestamp.
export function parseExpiryDate(value) {
  if (!value) return null
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : new Date(value.getTime())
  }

  const match = /^(\d{4})-(\d{2})-(\d{2})(?:T|$)/.exec(value)
  if (!match) return null
  const [, year, month, day] = match.map(Number)
  const date = new Date(year, month - 1, day)
  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day
    ? date : null
}

export function serializeExpiryDate(value) {
  const date = parseExpiryDate(value)
  if (!date) return null
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

export function formatExpiryDate(value) {
  const date = parseExpiryDate(value)
  if (!date) return 'Kein MHD'
  const [year, month, day] = serializeExpiryDate(date).split('-')
  const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate()
  return date.getDate() === lastDay ? `${month}-${year}` : `${day}.${month}.${year}`
}
