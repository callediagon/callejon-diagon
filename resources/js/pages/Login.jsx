import { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import Register from './Register';

export default function Login() {
    const { login } = useAuth();
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);
    const [showRegister, setShowRegister] = useState(false);

    if (showRegister) {
        return <Register onBack={() => setShowRegister(false)} />;
    }

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await login(username, password);
        } catch (err) {
            const msg = err.response?.data?.errors?.username?.[0]
                || err.response?.data?.message
                || 'Error al iniciar sesión.';
            setError(msg);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-gray-900 flex items-center justify-center px-4">
            <form
                onSubmit={handleSubmit}
                className="bg-gray-800 rounded-2xl p-6 w-full max-w-sm shadow-xl"
            >
                <h1 className="text-2xl font-bold text-yellow-400 text-center mb-6">
                    🧙‍♂️ Callejón Diagon
                </h1>

                {error && (
                    <div className="bg-red-900/50 border border-red-600 text-red-200 text-sm rounded-lg p-3 mb-4">
                        {error}
                    </div>
                )}

                <label className="block text-gray-300 text-sm mb-1">Usuario o #cuenta</label>
                <input
                    type="text"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    className="w-full bg-gray-700 text-white rounded-lg px-4 py-3 mb-4 focus:outline-none focus:ring-2 focus:ring-yellow-500"
                    autoComplete="username"
                    required
                />

                <label className="block text-gray-300 text-sm mb-1">Contraseña</label>
                <input
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="w-full bg-gray-700 text-white rounded-lg px-4 py-3 mb-6 focus:outline-none focus:ring-2 focus:ring-yellow-500"
                    autoComplete="current-password"
                    required
                />

                <button
                    type="submit"
                    disabled={loading}
                    className="w-full bg-yellow-500 hover:bg-yellow-400 text-gray-900 font-bold rounded-lg py-3 disabled:opacity-50"
                >
                    {loading ? 'Entrando...' : 'Entrar'}
                </button>

                <button
                    type="button"
                    onClick={() => setShowRegister(true)}
                    className="w-full mt-3 text-gray-400 hover:text-yellow-400 text-sm py-2"
                >
                    ¿No tienes cuenta? Regístrate
                </button>
            </form>
        </div>
    );
}