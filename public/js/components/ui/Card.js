import { jsx as _jsx } from "react/jsx-runtime";
export const Card = ({ children, className = '' }) => {
    return (_jsx("div", { className: `bg-white rounded-lg shadow-sm border border-gray-200 ${className}`, children: children }));
};
