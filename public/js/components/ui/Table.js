import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
export const Table = ({ columns, data, loading = false, emptyText = 'No data available', className = '' }) => {
    if (loading) {
        return (_jsx("div", { className: "flex justify-center items-center h-32", children: _jsx("div", { className: "animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" }) }));
    }
    if (data.length === 0) {
        return (_jsx("div", { className: "text-center py-8 text-gray-500", children: emptyText }));
    }
    return (_jsx("div", { className: `overflow-x-auto ${className}`, children: _jsxs("table", { className: "min-w-full divide-y divide-gray-200", children: [_jsx("thead", { className: "bg-gray-50", children: _jsx("tr", { children: columns.map((column) => (_jsx("th", { className: `px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider ${column.align === 'center' ? 'text-center' : column.align === 'right' ? 'text-right' : 'text-left'}`, style: { width: column.width }, children: column.title }, column.key))) }) }), _jsx("tbody", { className: "bg-white divide-y divide-gray-200", children: data.map((record, index) => (_jsx("tr", { className: "hover:bg-gray-50", children: columns.map((column) => (_jsx("td", { className: `px-6 py-4 whitespace-nowrap text-sm text-gray-900 ${column.align === 'center' ? 'text-center' : column.align === 'right' ? 'text-right' : 'text-left'}`, children: column.render
                                ? column.render(record[column.key], record)
                                : record[column.key] }, column.key))) }, index))) })] }) }));
};
