import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
export const Input = ({ label, error, helperText, className = '', ...props }) => {
    return (_jsxs("div", { className: "space-y-1", children: [label && (_jsx("label", { className: "block text-sm font-medium text-gray-700", children: label })), _jsx("input", { className: `block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${error ? 'border-red-300' : ''} ${className}`, ...props }), error && (_jsx("p", { className: "text-sm text-red-600", children: error })), helperText && !error && (_jsx("p", { className: "text-sm text-gray-500", children: helperText }))] }));
};
