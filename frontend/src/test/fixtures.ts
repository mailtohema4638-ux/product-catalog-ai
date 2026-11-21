import type { Category, Product } from '../api/types'

export function makeProduct(overrides: Partial<Product> & Pick<Product, 'id' | 'title'>): Product {
  return {
    description: `Description of ${overrides.title}.`,
    price: { amount_minor: 11400, currency: 'USD' },
    category: { slug: 'electronics', name: 'Electronics' },
    image_url: `https://images.test/${overrides.id}.jpg`,
    rating: { average: 4.8, count: 400 },
    ...overrides,
  }
}

export const categories: Category[] = [
  { slug: 'electronics', name: 'Electronics' },
  { slug: 'mens-clothing', name: "Men's Clothing" },
]

export const featured: Product[] = [
  makeProduct({ id: '12', title: 'WD 4TB Gaming Drive' }),
  makeProduct({ id: '11', title: 'Silicon Power 256GB SSD', price: { amount_minor: 10900, currency: 'USD' } }),
  makeProduct({ id: '3', title: 'Mens Cotton Jacket', category: { slug: 'mens-clothing', name: "Men's Clothing" } }),
  makeProduct({ id: '18', title: 'MBJ Short Sleeve Boat Neck', price: { amount_minor: 985, currency: 'USD' } }),
  makeProduct({ id: '5', title: 'Dragon Chain Bracelet', price: { amount_minor: 69500, currency: 'USD' } }),
]
