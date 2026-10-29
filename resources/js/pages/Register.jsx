import { useState } from 'react';
import api from '../api';

export default function Register({ onBack }) {
    const [form, setForm] = useState({
        username: '',
        name: '',
        last_name: '',
        password: '',
    });
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);
    const [loading, setLoading] = useState(false);

    const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

    const submit = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await api.get('/sanctum/csrf-cookie');
            await api.post('/api/register', form);
            setSuccess(true);
        } catch (err) {
            const errs = err.response?.data?.errors;
            setError(errs ? Object.values(errs).flat().join(' ') : (err.response?.data?.message || 'Error.'));
        } finally {
            setLoading(false);
        }
    };

    if (success) {
        return (
            <div className="min-h-screen bg-gray-900 flex items-center justify-center px-4">
                <div className="bg-gray-800 rounded-2xl p-6 w-full max-w-sm shadow-xl text-center">
                    <p className="text-4xl mb-4">✅</p>
                    <h2 className="text-xl font-bold text-yellow-400 mb-2">Registro enviado</h2>
                    <p className="text-gray-300 text-sm mb-6">
                        Tu solicitud está pendiente de aprobación. Un banquero, vice o director la revisará pronto. Cuando sea aprobada, podrás iniciar sesión con tu usuario y contraseña.
                    </p>
                    <button
                        onClick={onBack}
                        className="w-full bg-yellow-500 hover:bg-yellow-400 text-gray-900 font-bold rounded-lg py-3"
                    >
                        Volver al login
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-900 flex items-center justify-center px-4 py-8">
            <form
                onSubmit={submit}
                className="bg-gray-800 rounded-2xl p-6 w-full max-w-sm shadow-xl"
            >
                <h1 className="text-2xl font-bold text-yellow-400 text-center mb-2">
                    🧙‍♂️ Registro
                </h1>
                <p className="text-gray-400 text-xs text-center mb-6">
                    Crea tu cuenta. Un administrador debe aprobarla.
                </p>

                {error && (
                    <div className="bg-red-900/50 border border-red-600 text-red-200 text-sm rounded-lg p-3 mb-4">
                        {error}
                    </div>
                )}

                <input
                    type="text"
                    value={form.username}
                    onChange={set('username')}
                    placeholder="Usuario (sin espacios)"
                    className="w-full bg-gray-700 text-white rounded-lg px-4 py-3 mb-3"
                    required
                    minLength={3}
                />
                <input
                    type="text"
                    value={form.name}
                    onChange={set('name')}
                    placeholder="Nombre"
                    className="w-full bg-gray-700 text-white rounded-lg px-4 py-3 mb-3"
                    required
                />
                <input
                    type="text"
                    value={form.last_name}
                    onChange={set('last_name')}
                    placeholder="Apellido (opcional)"
                    className="w-full bg-gray-700 text-white rounded-lg px-4 py-3 mb-3"
                />

                <div className="relative mb-6">
                    <input
                        type={showPassword ? 'text' : 'password'}
                        value={form.password}
                        onChange={set('password')}
                        placeholder="Contraseña (mín 8)"
                        className="w-full bg-gray-700 text-white rounded-lg px-4 py-3 pr-12"
                        required
                        minLength={8}
                    />
                    <button
                        type="button"
                        onClick={() => setShowPassword(!showPassword)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-xl"
                    >
                        {showPassword ? '🙈' : '👁️'}
                    </button>
                </div>

                <button
                    type="submit"
                    disabled={loading}
                    className="w-full bg-yellow-500 hover:bg-yellow-400 text-gray-900 font-bold rounded-lg py-3 disabled:opacity-50 mb-3"
                >
                    {loading ? 'Enviando...' : 'Registrarme'}
                </button>

                <button
                    type="button"
                    onClick={onBack}
                    className="w-full text-gray-400 hover:text-yellow-400 text-sm py-2"
                >
                    ← Volver al login
                </button>
            </form>
        </div>
    );
}