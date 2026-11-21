import { useEffect, useRef, useState } from 'react'
import { ApiError, userMessage } from '../../api/client'
import type { Product } from '../../api/types'
import { ENQUIRY_FIELDS, validateEnquiry, type EnquiryField, type EnquiryValues, type FieldErrors } from './enquirySchema'
import { useSubmitEnquiry } from './useSubmitEnquiry'

interface EnquiryDialogProps {
  product: Product
  onClose: () => void
}

const EMPTY: EnquiryValues & { website: string } = { name: '', email: '', phone: '', message: '', website: '' }

export function EnquiryDialog({ product, onClose }: EnquiryDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null)
  const [values, setValues] = useState(EMPTY)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const mutation = useSubmitEnquiry()

  useEffect(() => {
    const dialog = dialogRef.current
    if (dialog && !dialog.open) {
      dialog.showModal()
    }
  }, [])

  function close() {
    dialogRef.current?.close()
  }

  function update(field: keyof typeof EMPTY, value: string) {
    setValues((current) => ({ ...current, [field]: value }))
  }

  function submit(event: React.SyntheticEvent<HTMLFormElement>) {
    event.preventDefault()
    if (mutation.isPending) return

    const result = validateEnquiry(values)
    if (!result.ok) {
      setFieldErrors(result.errors)
      const first = ENQUIRY_FIELDS.find((field) => result.errors[field])
      if (first) document.getElementById(`enquiry-${first}`)?.focus()
      return
    }

    setFieldErrors({})
    mutation.mutate(
      {
        product_id: product.id,
        name: result.data.name,
        email: result.data.email,
        phone: result.data.phone || undefined,
        message: result.data.message || undefined,
        website: values.website,
      },
      {
        onError: (error) => {
          if (error instanceof ApiError && error.kind === 'validation' && error.details) {
            const serverErrors: FieldErrors = {}
            for (const field of ENQUIRY_FIELDS) {
              const message = error.details[field]?.[0]
              if (message) serverErrors[field] = message
            }
            setFieldErrors(serverErrors)
          }
        },
      },
    )
  }

  return (
    <dialog
      ref={dialogRef}
      className="dialog"
      aria-labelledby="enquiry-title"
      onClose={onClose}
      onClick={(event) => event.target === dialogRef.current && close()}
    >
      {mutation.isSuccess ? (
        <div className="dialog__body" role="status">
          <h2 id="enquiry-title">Thank you</h2>
          <p>
            Your enquiry about <strong>{product.title}</strong> has been sent. Our sales team will be in touch soon.
          </p>
          <p className="state__meta">Reference: {mutation.data.reference}</p>
          <button type="button" className="button" onClick={close}>
            Close
          </button>
        </div>
      ) : (
        <form className="dialog__body" onSubmit={submit} noValidate>
          <h2 id="enquiry-title">Enquire about this product</h2>
          <p className="dialog__product">{product.title}</p>

          {mutation.isError && (
            <p className="form-error" role="alert">
              {userMessage(mutation.error)}
            </p>
          )}

          <Field id="name" label="Your name" error={fieldErrors.name} required>
            <input
              id="enquiry-name"
              name="name"
              autoComplete="name"
              value={values.name}
              onChange={(event) => update('name', event.target.value)}
              aria-invalid={Boolean(fieldErrors.name)}
              aria-describedby={fieldErrors.name ? 'enquiry-name-error' : undefined}
              maxLength={100}
            />
          </Field>

          <Field id="email" label="Email address" error={fieldErrors.email} required>
            <input
              id="enquiry-email"
              name="email"
              type="email"
              autoComplete="email"
              value={values.email}
              onChange={(event) => update('email', event.target.value)}
              aria-invalid={Boolean(fieldErrors.email)}
              aria-describedby={fieldErrors.email ? 'enquiry-email-error' : undefined}
              maxLength={254}
            />
          </Field>

          <Field id="phone" label="Phone (optional)" error={fieldErrors.phone}>
            <input
              id="enquiry-phone"
              name="phone"
              type="tel"
              autoComplete="tel"
              value={values.phone}
              onChange={(event) => update('phone', event.target.value)}
              aria-invalid={Boolean(fieldErrors.phone)}
              aria-describedby={fieldErrors.phone ? 'enquiry-phone-error' : undefined}
              maxLength={30}
            />
          </Field>

          <Field id="message" label="Message (optional)" error={fieldErrors.message}>
            <textarea
              id="enquiry-message"
              name="message"
              rows={4}
              value={values.message}
              onChange={(event) => update('message', event.target.value)}
              aria-invalid={Boolean(fieldErrors.message)}
              aria-describedby={fieldErrors.message ? 'enquiry-message-error' : undefined}
              maxLength={1000}
            />
          </Field>

          {/* Honeypot: invisible to people and assistive tech; bots fill it in. */}
          <div className="honeypot" aria-hidden="true">
            <label>
              Website
              <input
                name="website"
                tabIndex={-1}
                autoComplete="off"
                value={values.website}
                onChange={(event) => update('website', event.target.value)}
              />
            </label>
          </div>

          <div className="dialog__actions">
            <button type="button" className="button button--secondary" onClick={close}>
              Cancel
            </button>
            <button type="submit" className="button" disabled={mutation.isPending}>
              {mutation.isPending ? 'Sending…' : 'Send enquiry'}
            </button>
          </div>
        </form>
      )}
    </dialog>
  )
}

interface FieldProps {
  id: EnquiryField
  label: string
  error?: string
  required?: boolean
  children: React.ReactNode
}

function Field({ id, label, error, required, children }: FieldProps) {
  return (
    <div className="field">
      <label htmlFor={`enquiry-${id}`}>
        {label}
        {required && <span aria-hidden="true"> *</span>}
      </label>
      {children}
      {error && (
        <p id={`enquiry-${id}-error`} className="field__error">
          {error}
        </p>
      )}
    </div>
  )
}
