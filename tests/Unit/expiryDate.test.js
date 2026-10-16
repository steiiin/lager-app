import assert from 'node:assert/strict'
import { test } from 'node:test'
import { nextTick } from 'vue'
import { useExpiryDate } from '../../resources/js/Composables/useExpiryDate.js'
import { formatExpiryDate, parseExpiryDate, serializeExpiryDate } from '../../resources/js/Utils/expiryDateUtils.js'

test('automatic day follows month-end and leap years', () => {
  const expiry = useExpiryDate()
  expiry.reset('2028-01-31')
  for (const [year, month, day] of [[2028, 2, 29], [2027, 2, 28], [2027, 4, 30], [2027, 5, 31]]) {
    expiry.year.value = year
    expiry.month.value = month
    assert.equal(expiry.day.value, day)
    assert.equal(expiry.date.value.getDate(), day)
  }
})

test('changing month or year resets an explicit day to month-end', () => {
  const expiry = useExpiryDate()
  expiry.reset('2028-04-15')
  expiry.month.value = 5
  assert.equal(serializeExpiryDate(expiry.date.value), '2028-05-31')
  expiry.day.value = 15
  assert.equal(serializeExpiryDate(expiry.date.value), '2028-05-15')
  expiry.month.value = 2
  assert.equal(expiry.day.value, 29)
  expiry.day.value = 15
  expiry.year.value = 2027
  assert.equal(expiry.day.value, 28)
  expiry.day.value = 15
  expiry.year.value = 2028
  assert.equal(expiry.day.value, 29)
  expiry.month.value = 3
  assert.equal(expiry.day.value, 31)
})

test('clearing a manual day restores month-end', () => {
  const expiry = useExpiryDate()
  expiry.reset('2028-03-15')
  expiry.day.value = null
  assert.equal(expiry.day.value, 31)
  expiry.month.value = 4
  assert.equal(expiry.day.value, 30)
  expiry.day.value = 15
  expiry.month.value = 5
  assert.equal(expiry.day.value, 31)
  expiry.day.value = 15
  expiry.day.value = ''
  assert.equal(expiry.day.value, 31)
})

test('opening another entry or creating resets state without delayed overwrites', async () => {
  const expiry = useExpiryDate()
  expiry.reset('2027-02-28')
  expiry.reset('2028-01-30T00:00:00.000000Z')
  await nextTick()
  assert.equal(serializeExpiryDate(expiry.date.value), '2028-01-30')
  expiry.reset('2028-01-15')
  await nextTick()
  assert.equal(serializeExpiryDate(expiry.date.value), '2028-01-15')
  expiry.reset('2028-02-29')
  expiry.month.value = 3
  assert.equal(expiry.day.value, 31)
  expiry.reset('2028-03-15')
  expiry.reset()
  const now = new Date()
  assert.equal(expiry.year.value, now.getFullYear())
  assert.equal(expiry.month.value, now.getMonth() + 1)
  assert.equal(expiry.day.value, new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate())
})

test('invalid input never produces a saveable date', () => {
  const expiry = useExpiryDate()
  for (const [field, invalidValues] of [
    ['day', [0, -1, 32, 1.5, NaN]],
    ['month', [null, 0, 13, 1.5, NaN]],
    ['year', [null, 0, 10000, 2028.5, NaN]],
  ]) {
    for (const value of invalidValues) {
      expiry.reset('2028-01-31')
      expiry[field].value = value
      assert.equal(expiry.date.value, null, `${field}=${value}`)
    }
  }
})

test('date-only and API dates round-trip in winter and summer without timezone shifts', () => {
  for (const value of ['2026-01-15', '2026-07-15', '2028-02-29']) {
    const [year, month, day] = value.split('-').map(Number)
    assert.equal(serializeExpiryDate(new Date(year, month - 1, day)), value)
    assert.equal(serializeExpiryDate(parseExpiryDate(value)), value)
    assert.equal(serializeExpiryDate(parseExpiryDate(`${value}T00:00:00.000000Z`)), value)
  }
  assert.equal(parseExpiryDate('2027-02-29'), null)
  assert.equal(parseExpiryDate('invalid'), null)
})

test('labels include the day only before month-end', () => {
  assert.equal(formatExpiryDate('2026-07-15'), '15.07.2026')
  assert.equal(formatExpiryDate('2026-07-31'), '07-2026')
  assert.equal(formatExpiryDate('2028-02-28'), '28.02.2028')
  assert.equal(formatExpiryDate('2028-02-29'), '02-2028')
  assert.equal(formatExpiryDate(null), 'Kein MHD')
})
