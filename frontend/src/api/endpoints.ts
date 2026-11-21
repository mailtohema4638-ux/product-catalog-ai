import { apiRequest } from './client'
import type { Category, CategoryPage, EnquiryInput, EnquiryReceipt, Product } from './types'

export const catalogueApi = {
  featured: () => apiRequest<Product[]>('/featured'),
  categories: () => apiRequest<Category[]>('/categories'),
  category: (slug: string) => apiRequest<CategoryPage>(`/categories/${encodeURIComponent(slug)}`),
  search: (term: string) => apiRequest<Product[]>(`/products?${new URLSearchParams({ q: term })}`),
  product: (id: string) => apiRequest<Product>(`/products/${encodeURIComponent(id)}`),
}

export const enquiryApi = {
  submit: (input: EnquiryInput) =>
    apiRequest<EnquiryReceipt>('/enquiries', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(input),
    }),
}
