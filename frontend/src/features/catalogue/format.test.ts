import { formatMoney } from './format'

describe('formatMoney', () => {
  it.each([
    [{ amount_minor: 11400, currency: 'USD' }, '$114.00'],
    [{ amount_minor: 985, currency: 'USD' }, '$9.85'],
    [{ amount_minor: 69500, currency: 'USD' }, '$695.00'],
    [{ amount_minor: 5, currency: 'USD' }, '$0.05'],
    [{ amount_minor: 99999, currency: 'USD' }, '$999.99'],
  ])('formats %j as %s', (money, expected) => {
    expect(formatMoney(money)).toBe(expected)
  })
})
