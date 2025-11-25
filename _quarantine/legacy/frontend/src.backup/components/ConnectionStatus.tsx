import { useState, useEffect } from 'react'
import { Wifi, WifiOff, RefreshCw } from 'lucide-react'
import { useWebSocket } from '../services/websocketService'

export default function ConnectionStatus() {
  const [isConnected, setIsConnected] = useState(false)
  const [isReconnecting, setIsReconnecting] = useState(false)
  const { isConnected: wsConnected, reconnect } = useWebSocket()

  useEffect(() => {
    setIsConnected(wsConnected())
  }, [wsConnected])

  const handleReconnect = () => {
    setIsReconnecting(true)
    reconnect()
    
    // Reset reconnecting state after a delay
    setTimeout(() => {
      setIsReconnecting(false)
    }, 2000)
  }

  if (isConnected) {
    return (
      <div className="flex items-center space-x-2 text-green-600 dark:text-green-400">
        <Wifi className="h-4 w-4" />
        <span className="text-sm font-medium">Connected</span>
      </div>
    )
  }

  return (
    <div className="flex items-center space-x-2">
      <div className="flex items-center space-x-2 text-red-600 dark:text-red-400">
        <WifiOff className="h-4 w-4" />
        <span className="text-sm font-medium">Disconnected</span>
      </div>
      <button
        onClick={handleReconnect}
        disabled={isReconnecting}
        className="flex items-center space-x-1 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 disabled:opacity-50"
      >
        <RefreshCw className={cn('h-4 w-4', isReconnecting && 'animate-spin')} />
        <span>{isReconnecting ? 'Reconnecting...' : 'Reconnect'}</span>
      </button>
    </div>
  )
}

function cn(...classes: (string | undefined | null | false)[]): string {
  return classes.filter(Boolean).join(' ')
}
