// Documents hooks
export {
  useDocuments,
  useDocumentsByProject,
  useSearchDocuments,
  useDocumentStats,
  useCreateDocument,
  useUpdateDocument,
  useDeleteDocument,
} from './useDocuments';

// Document detail hooks
export {
  useDocument,
  useDocumentVersions,
  useUploadDocumentVersion,
  useRevertDocumentVersion,
  useApproveDocumentForClient,
  useDownloadDocumentVersion,
} from './useDocument';

// Form hooks
export {
  useCreateDocumentForm,
  useUpdateDocumentForm,
  useUploadVersionForm,
  useFileDropzone,
} from './useDocumentForm';