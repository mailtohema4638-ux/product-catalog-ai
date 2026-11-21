import { useQuery } from '@tanstack/react-query'
import { catalogueApi } from '../../api/endpoints'

export const useFeatured = () => useQuery({ queryKey: ['featured'], queryFn: catalogueApi.featured })

export const useCategories = () => useQuery({ queryKey: ['categories'], queryFn: catalogueApi.categories })

export const useCategory = (slug: string) =>
  useQuery({ queryKey: ['category', slug], queryFn: () => catalogueApi.category(slug) })

export const useProduct = (id: string) =>
  useQuery({ queryKey: ['product', id], queryFn: () => catalogueApi.product(id) })

export const useSearch = (term: string) =>
  useQuery({ queryKey: ['search', term], queryFn: () => catalogueApi.search(term), enabled: term.length > 0 })
