import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { AuthProvider, useAuth } from './context/AuthContext';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';

function Root() {
    const { user, loading } = useAuth();

    if (loading) {
        return (
            <div className="min-h-screen bg-gray-900 flex items-center justify-center text-white">
                Cargando...
            </div>
        );
    }

    return user ? <Dashboard /> : <Login />;
}

const el = document.getElementById('app');
if (el) {
    createRoot(el).render(
        <AuthProvider>
            <Root />
        </AuthProvider>
    );
}