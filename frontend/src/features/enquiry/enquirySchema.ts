import { z } from 'zod'

const PHONE = /^[0-9+()\-.\s]{5,30}$/

/** Mirrors the server rules for fast feedback. The server remains the authority. */
export const enquirySchema = z.object({
  name: z
    .string()
    .trim()
    .min(2, 'Please enter your name (at least 2 characters).')
    .max(100, 'Name must be 100 characters or fewer.'),
  email: z
    .string()
    .trim()
    .min(1, 'Please enter your email address.')
    .max(254, 'Email address is too long.')
    .refine((value) => z.email().safeParse(value).success, 'Please enter a valid email address.'),
  phone: z.string().trim().refine((value) => value === '' || PHONE.test(value), 'Please enter a valid phone number.'),
  message: z.string().trim().max(1000, 'Message must be 1000 characters or fewer.'),
})

export type EnquiryField = keyof z.input<typeof enquirySchema>
export type EnquiryValues = z.input<typeof enquirySchema>
export type FieldErrors = Partial<Record<EnquiryField, string>>

export const ENQUIRY_FIELDS: EnquiryField[] = ['name', 'email', 'phone', 'message']

export type EnquiryValidation =
  | { ok: true; data: z.output<typeof enquirySchema> }
  | { ok: false; errors: FieldErrors }

export function validateEnquiry(values: EnquiryValues): EnquiryValidation {
  const result = enquirySchema.safeParse(values)
  if (result.success) {
    return { ok: true, data: result.data }
  }

  const errors: FieldErrors = {}
  for (const issue of result.error.issues) {
    const field = issue.path[0] as EnquiryField
    errors[field] ??= issue.message
  }
  return { ok: false, errors }
}
