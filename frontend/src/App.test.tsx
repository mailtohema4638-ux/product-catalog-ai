import { render, screen } from '@testing-library/react'
import App from './App'

describe('App', () => {
  it('renders the storefront heading', () => {
    render(<App />)
    expect(screen.getByRole('heading', { name: /product catalogue/i })).toBeInTheDocument()
  })
})
