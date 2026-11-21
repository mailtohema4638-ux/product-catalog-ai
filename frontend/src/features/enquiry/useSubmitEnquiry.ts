import { useMutation } from '@tanstack/react-query'
import { enquiryApi } from '../../api/endpoints'

export const useSubmitEnquiry = () => useMutation({ mutationFn: enquiryApi.submit })
