import type { SVGProps } from 'react'

type IconComponent = (props: SVGProps<SVGSVGElement>) => JSX.Element

const BaseIcon: IconComponent = ({ className, ...props }) => (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth={1.5}
    className={className}
    {...props}
  >
    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16M4 12h16" />
  </svg>
)

export const ArrowDownTrayIcon = BaseIcon
export const ArrowLeftIcon = BaseIcon
export const ArrowPathIcon = BaseIcon
export const ArrowRightOnRectangleIcon = BaseIcon
export const ArrowUpTrayIcon = BaseIcon
export const ArrowUturnLeftIcon = BaseIcon
export const CalendarIcon = BaseIcon
export const CameraIcon = BaseIcon
export const ChartBarIcon = BaseIcon
export const ChartPieIcon = BaseIcon
export const CheckCircleIcon = BaseIcon
export const CheckIcon = BaseIcon
export const ClockIcon = BaseIcon
export const Cog6ToothIcon = BaseIcon
export const CurrencyDollarIcon = BaseIcon
export const DocumentArrowUpIcon = BaseIcon
export const DocumentChartBarIcon = BaseIcon
export const DocumentTextIcon = BaseIcon
export const ExclamationTriangleIcon = BaseIcon
export const EyeIcon = BaseIcon
export const EyeSlashIcon = BaseIcon
export const FolderIcon = BaseIcon
export const FunnelIcon = BaseIcon
export const HomeIcon = BaseIcon
export const InformationCircleIcon = BaseIcon
export const KeyIcon = BaseIcon
export const MagnifyingGlassIcon = BaseIcon
export const MinusIcon = BaseIcon
export const PencilIcon = BaseIcon
export const PlusIcon = BaseIcon
export const ShieldCheckIcon = BaseIcon
export const ShoppingCartIcon = BaseIcon
export const TrashIcon = BaseIcon
export const UserGroupIcon = BaseIcon
export const UserIcon = BaseIcon
export const UsersIcon = BaseIcon
export const WrenchScrewdriverIcon = BaseIcon
export const XCircleIcon = BaseIcon
export const XMarkIcon = BaseIcon
export const BellIcon = BaseIcon
export const BuildingOfficeIcon = BaseIcon
export const ClipboardDocumentListIcon = BaseIcon
