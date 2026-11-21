export interface Money {
  amount_minor: number
  currency: string
}

export interface Category {
  slug: string
  name: string
}

export interface Product {
  id: string
  title: string
  description: string
  price: Money
  category: Category
  image_url: string | null
  rating: { average: number; count: number }
}

export interface CategoryPage {
  category: Category
  top_rated: Product[]
  others: Product[]
}

export interface EnquiryInput {
  product_id: string
  name: string
  email: string
  phone?: string
  message?: string
  /** Honeypot: always empty for people. */
  website?: string
}

export interface EnquiryReceipt {
  reference: string
}
