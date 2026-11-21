import type { Money } from '../../api/types'

/** Prices arrive as integer minor units (cents); format only at the edge. */
export function formatMoney(money: Money): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: money.currency }).format(money.amount_minor / 100)
}
