# ZENA Manage Frontend

A modern React frontend for the ZENA Manage project management system.

## Features

- 🔐 **Authentication**: Login and registration with JWT
- 👥 **User Management**: Complete CRUD operations for users
- 🎨 **Modern UI**: Built with React 18, TypeScript, and Tailwind CSS
- 📱 **Responsive**: Mobile-first design
- 🚀 **Fast**: Vite for lightning-fast development
- 🔄 **State Management**: Zustand for simple state management
- 📊 **Data Fetching**: TanStack Query for server state
- 🎯 **Type Safety**: Full TypeScript support

## Tech Stack

- **React 18** - UI library
- **TypeScript** - Type safety
- **Vite** - Build tool
- **Tailwind CSS** - Styling
- **React Router** - Routing
- **Zustand** - State management
- **TanStack Query** - Data fetching
- **React Hook Form** - Form handling
- **Zod** - Validation
- **Lucide React** - Icons
- **Axios** - HTTP client

## Getting Started

### Prerequisites

- Node.js 18+ 
- npm or yarn

### Installation

1. Install dependencies:
```bash
npm install
```

2. Start development server:
```bash
npm run dev
```

3. Open [http://localhost:3000](http://localhost:3000) in your browser

### Building for Production

```bash
npm run build
```

### Preview Production Build

```bash
npm run preview
```

## Project Structure

```
src/
├── components/          # Reusable UI components
├── pages/              # Page components
├── services/           # API services
├── stores/             # Zustand stores
├── types/              # TypeScript types
├── lib/                # Utility functions
└── main.tsx           # App entry point
```

## API Integration

The frontend integrates with the Laravel backend API:

- **Base URL**: `/api/v1`
- **Authentication**: JWT Bearer tokens
- **Error Handling**: Automatic error handling with toast notifications
- **Request/Response**: Axios interceptors for token management

## Available Scripts

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run preview` - Preview production build
- `npm run lint` - Run ESLint

## Environment Variables

Create a `.env` file in the root directory:

```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

## Features Implemented

### ✅ Authentication
- User login with email/password
- User registration with company information
- JWT token management
- Protected routes
- Automatic token refresh

### ✅ User Management
- User listing with pagination
- User details view
- Search and filtering
- Responsive table design

### ✅ UI Components
- Modern, accessible design
- Responsive layout
- Loading states
- Error handling
- Toast notifications

### 🚧 Coming Soon
- Project management
- Task management
- Real-time updates
- Advanced filtering
- Export functionality

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is licensed under the MIT License.
