import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { userService } from '../services/userService'

export default function UsersTestPage() {
  const [testData, setTestData] = useState<any>(null)

  const { data, isLoading, error } = useQuery({
    queryKey: ['users-test'],
    queryFn: () => userService.getUsers(),
  })

  const handleTest = () => {
    setTestData({ 
      data: data, 
      isLoading, 
      error: error?.message,
      timestamp: new Date().toISOString()
    })
  }

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold">Users Test Page</h1>
      
      <div className="space-y-2">
        <div>Loading: {isLoading ? 'Yes' : 'No'}</div>
        <div>Error: {error?.message || 'None'}</div>
        <div>Data Count: {data?.data?.length || 0}</div>
      </div>

      <button 
        onClick={handleTest}
        className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
      >
        Test API Call
      </button>

      {testData && (
        <div className="bg-gray-100 p-4 rounded">
          <h3 className="font-bold mb-2">Test Results:</h3>
          <pre className="text-sm overflow-auto">
            {JSON.stringify(testData, null, 2)}
          </pre>
        </div>
      )}

      {data?.data && (
        <div className="space-y-2">
          <h3 className="font-bold">Users Data:</h3>
          {data.data.slice(0, 3).map((user: any) => (
            <div key={user.id} className="bg-gray-50 p-2 rounded">
              <div>Name: {user.name}</div>
              <div>Email: {user.email}</div>
              <div>Status: {user.status}</div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
