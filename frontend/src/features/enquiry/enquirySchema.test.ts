import { validateEnquiry } from './enquirySchema'

const valid = { name: 'Jane Doe', email: 'jane@example.com', phone: '', message: '' }

describe('validateEnquiry', () => {
  it('accepts the minimum: a name and an email', () => {
    expect(validateEnquiry(valid)).toMatchObject({ ok: true })
  })

  it('trims whitespace', () => {
    const result = validateEnquiry({ ...valid, name: '  Jane  ', email: ' jane@example.com ' })

    expect(result).toMatchObject({ ok: true, data: { name: 'Jane', email: 'jane@example.com' } })
  })

  it.each([
    ['name', '', 'Please enter your name (at least 2 characters).'],
    ['name', 'J', 'Please enter your name (at least 2 characters).'],
    ['name', 'x'.repeat(101), 'Name must be 100 characters or fewer.'],
    ['email', '', 'Please enter your email address.'],
    ['email', 'nope', 'Please enter a valid email address.'],
    ['email', 'a@b', 'Please enter a valid email address.'],
    ['phone', 'abc', 'Please enter a valid phone number.'],
    ['phone', '123', 'Please enter a valid phone number.'],
    ['message', 'x'.repeat(1001), 'Message must be 1000 characters or fewer.'],
  ])('reports one clear message for invalid %s (%j)', (field, value, message) => {
    const result = validateEnquiry({ ...valid, [field]: value })

    expect(result).toEqual({ ok: false, errors: { [field]: message } })
  })

  it.each(['+1 555 0100', '(020) 7946-0000', '07700.900.123'])('accepts the phone number %s', (phone) => {
    expect(validateEnquiry({ ...valid, phone })).toMatchObject({ ok: true })
  })

  it('reports every invalid field at once', () => {
    const result = validateEnquiry({ name: '', email: '', phone: 'x', message: '' })

    expect(result.ok).toBe(false)
    expect(Object.keys(!result.ok ? result.errors : {}).sort()).toEqual(['email', 'name', 'phone'])
  })
})
