import { Link, useParams } from 'react-router-dom';

export default function ContractDetailPlaceholderPage() {
  const { projectId, contractId } = useParams<{ projectId: string; contractId: string }>();

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Contract Detail</h1>
        <p className="mt-1 text-sm text-gray-600">Coming soon (UI-2).</p>
      </div>

      <div className="rounded-lg border border-gray-200 bg-white p-6">
        <p className="text-sm text-gray-700">
          <span className="font-semibold">Project ID:</span> {projectId || '-'}
        </p>
        <p className="mt-2 text-sm text-gray-700">
          <span className="font-semibold">Contract ID:</span> {contractId || '-'}
        </p>
      </div>

      <Link
        to={`/app/projects/${projectId}/contracts`}
        className="inline-flex rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
      >
        Back to Contracts
      </Link>
    </div>
  );
}
