import { useCallback, useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import api from '../api';

const BANK_ROLES = ['banquero', 'director', 'vice'];
const APPROVER_ROLES = ['director', 'vice'];

export default function Dashboard() {
    const { user, logout } = useAuth();
    const [tab, setTab] = useState('users');

    const canBank = user.roles.some((r) => BANK_ROLES.includes(r));
    const canApprove = user.roles.some((r) => APPROVER_ROLES.includes(r));

    return (
        <div className="min-h-screen bg-gray-900 text-white p-4 pb-12">
            <div className="max-w-md mx-auto">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-xl font-bold text-yellow-400">Callejón Diagon</h1>
                    <div className="flex items-center gap-2">
                        <NotificationBell />
                        <button onClick={logout} className="bg-red-700 hover:bg-red-600 px-4 py-2 rounded-lg text-sm">
                            Salir
                        </button>
                    </div>
                </div>

                <div className="bg-gray-800 rounded-2xl p-4 mb-4">
                    <p className="text-gray-400 text-sm">Hola, {user.name} · @{user.username} · #{user.account_number}</p>
                    <p className="text-3xl font-bold text-yellow-400 mt-1">{user.balance} 🪙</p>
                    <div className="flex flex-wrap gap-1 mt-2">
                        {user.roles.map((r) => (
                            <span key={r} className="bg-yellow-500/20 text-yellow-300 px-2 py-0.5 rounded-full text-xs">
                                {r}
                            </span>
                        ))}
                    </div>
                </div>

                {!canBank && <StudentPanel />}

                {canBank && (
                    <div className="bg-gray-800 rounded-2xl p-4 mb-4">
                        <MyTransactions />
                    </div>
                )}

                {canBank && (
                    <div className="bg-gray-800 rounded-2xl p-4">
                        <div className="flex gap-2 mb-4 flex-wrap">
                            <TabBtn active={tab === 'users'} onClick={() => setTab('users')}>Usuarios</TabBtn>
                            <TabBtn active={tab === 'requests'} onClick={() => setTab('requests')}>Cuentas</TabBtn>
                            <TabBtn active={tab === 'transfers'} onClick={() => setTab('transfers')}>Transferencias</TabBtn>
                            <TabBtn active={tab === 'myhistory'} onClick={() => setTab('myhistory')}>Mi historial</TabBtn>
                            <TabBtn active={tab === 'audit'} onClick={() => setTab('audit')}>Auditoría</TabBtn>
                            {canApprove && (
                                <TabBtn active={tab === 'create'} onClick={() => setTab('create')}>Crear</TabBtn>
                            )}
                        </div>

                        {tab === 'users' && <UsersList />}
                        {tab === 'requests' && <RequestsList canApprove={canApprove} />}
                        {tab === 'transfers' && <PendingTransfers />}
                        {tab === 'myhistory' && <MyTransactions />}
                        {tab === 'audit' && <AuditLog />}
                        {tab === 'create' && canApprove && <CreateUserForm />}
                    </div>
                )}
            </div>
        </div>
    );
}

function TabBtn({ active, onClick, children }) {
    return (
        <button
            onClick={onClick}
            className={`px-3 py-2 rounded-lg text-sm font-medium ${
                active ? 'bg-yellow-500 text-gray-900' : 'bg-gray-700 text-gray-300'
            }`}
        >
            {children}
        </button>
    );
}

// ==== ESTUDIANTE ====
function StudentPanel() {
    const [tab, setTab] = useState('transfer');

    return (
        <div className="bg-gray-800 rounded-2xl p-4">
            <div className="flex gap-2 mb-4 flex-wrap">
                <TabBtn active={tab === 'transfer'} onClick={() => setTab('transfer')}>Transferir</TabBtn>
                <TabBtn active={tab === 'history'} onClick={() => setTab('history')}>Transferencias</TabBtn>
                <TabBtn active={tab === 'balance'} onClick={() => setTab('balance')}>Historial</TabBtn>
            </div>
            {tab === 'transfer' && <TransferForm />}
            {tab === 'history' && <MyTransfers />}
            {tab === 'balance' && <MyTransactions />}
        </div>
    );
}

function TransferForm() {
    const { user } = useAuth();
    const [toUsername, setToUsername] = useState('');
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('');
    const [msg, setMsg] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        setMsg(null);
        setError(null);
        setLoading(true);
        try {
            await api.post('/api/transfers', {
                to_username: toUsername,
                amount: parseInt(amount, 10),
                reason: reason || null,
            });
            setMsg('Solicitud enviada. Esperando aprobación del banquero.');
            setToUsername('');
            setAmount('');
            setReason('');
        } catch (err) {
            const errs = err.response?.data?.errors;
            setError(errs ? Object.values(errs).flat().join(' ') : (err.response?.data?.message || 'Error.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <p className="text-gray-400 text-xs">
                Saldo actual: <span className="text-yellow-400 font-bold">{user.balance} 🪙</span>
                <br />(transferir más del saldo se permite, pero el banquero decide)
            </p>

            {msg && <div className="bg-green-900/50 text-green-200 text-sm rounded p-2">{msg}</div>}
            {error && <div className="bg-red-900/50 text-red-200 text-sm rounded p-2">{error}</div>}

            <input value={toUsername} onChange={(e) => setToUsername(e.target.value)} placeholder="Usuario o #cuenta (ej: harry o 002)" className="w-full bg-gray-700 text-white rounded-lg px-4 py-3" required />

            <input
                type="number"
                min="1"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                placeholder="Cantidad de galeones"
                className="w-full bg-gray-700 text-white rounded-lg px-4 py-3"
                required
            />

            <input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Motivo (opcional)" className="w-full bg-gray-700 text-white rounded-lg px-4 py-3" />

            <button type="submit" disabled={loading} className="w-full bg-yellow-500 hover:bg-yellow-400 text-gray-900 font-bold rounded-lg py-3 disabled:opacity-50">
                {loading ? 'Enviando...' : 'Solicitar transferencia'}
            </button>
        </form>
    );
}

function MyTransfers() {
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);

    const load = useCallback(() => {
        api.get('/api/transfers/mine')
            .then((res) => setData(res.data))
            .catch((err) => setError(err.response?.data?.message || 'Error.'));
    }, []);

    useEffect(() => { load(); }, [load]);

    const cancel = async (id) => {
        if (!confirm('¿Cancelar esta transferencia?')) return;
        try {
            await api.post(`/api/transfers/${id}/cancel`);
            load();
        } catch (err) {
            alert(err.response?.data?.message || 'Error.');
        }
    };

    if (error) return <p className="text-red-400 text-sm">{error}</p>;
    if (!data) return <p className="text-gray-400 text-sm">Cargando...</p>;

    const all = [...data.sent.map((t) => ({ ...t, dir: 'sent' })), ...data.received.map((t) => ({ ...t, dir: 'received' }))]
        .sort((a, b) => b.created_at.localeCompare(a.created_at));

    if (all.length === 0) return <p className="text-gray-400 text-sm">Sin transferencias.</p>;

    return (
        <div className="space-y-2">
            {all.map((t) => (
                <div key={`${t.dir}-${t.id}`} className="bg-gray-700 rounded-lg p-3 text-sm">
                    <div className="flex justify-between">
                        <span className="text-gray-400">{t.dir === 'sent' ? '→' : '←'} @{t.dir === 'sent' ? t.to_username : t.from_username}</span>
                        <span className={`font-bold ${t.dir === 'sent' ? 'text-red-400' : 'text-green-400'}`}>
                            {t.dir === 'sent' ? '-' : '+'}{t.amount} 🪙
                        </span>
                    </div>
                    <div className="text-xs text-gray-500 mt-1">
                        {t.status} · {new Date(t.created_at).toLocaleString('es-ES')}
                    </div>
                    {t.status === 'pending' && t.dir === 'sent' && (
                        <button onClick={() => cancel(t.id)} className="mt-2 text-xs bg-red-700 hover:bg-red-600 px-3 py-1 rounded">
                            Cancelar
                        </button>
                    )}
                </div>
            ))}
        </div>
    );
}

// ==== BANQUERO ====
function PendingTransfers() {
    const [transfers, setTransfers] = useState(null);
    const [error, setError] = useState(null);

    const load = useCallback(() => {
        api.get('/api/bank/transfers/pending')
            .then((res) => setTransfers(res.data.transfers))
            .catch((err) => setError(err.response?.data?.message || 'Error.'));
    }, []);

    useEffect(() => { load(); }, [load]);

    const action = async (id, type) => {
        try {
            await api.post(`/api/bank/transfers/${id}/${type}`);
            load();
        } catch (err) {
            alert(err.response?.data?.message || 'Error.');
        }
    };

    if (error) return <p className="text-red-400 text-sm">{error}</p>;
    if (!transfers) return <p className="text-gray-400 text-sm">Cargando...</p>;
    if (transfers.length === 0) return <p className="text-gray-400 text-sm">No hay transferencias pendientes.</p>;

    return (
        <div className="space-y-2">
            {transfers.map((t) => (
                <div key={t.id} className="bg-gray-700 rounded-lg p-3">
                    <div className="text-sm">
                        <span className="text-gray-300">@{t.from_username}</span>
                        <span className="text-gray-500"> → </span>
                        <span className="text-gray-300">@{t.to_username}</span>
                        <span className="float-right font-bold text-yellow-400">{t.amount} 🪙</span>
                    </div>
                    {t.reason && <p className="text-xs text-gray-400 mt-1">"{t.reason}"</p>}
                    <p className="text-xs text-gray-500 mt-1">
                        Expira: {new Date(t.expires_at).toLocaleString('es-ES')}
                    </p>
                    <div className="flex gap-2 mt-2">
                        <button onClick={() => action(t.id, 'approve')} className="flex-1 bg-green-700 hover:bg-green-600 py-2 rounded text-sm">Aprobar</button>
                        <button onClick={() => action(t.id, 'reject')} className="flex-1 bg-red-700 hover:bg-red-600 py-2 rounded text-sm">Rechazar</button>
                    </div>
                </div>
            ))}
        </div>
    );
}

// ==== Resto (sin cambios) ====
function UsersList() {
    const { user: me } = useAuth();
    const [users, setUsers] = useState(null);
    const [error, setError] = useState(null);
    const [adjusting, setAdjusting] = useState(null);
    const [resetting, setResetting] = useState(null);

    const load = useCallback(() => {
        api.get('/api/bank/users')
            .then((res) => setUsers(res.data.users))
            .catch((err) => setError(err.response?.data?.message || 'Error.'));
    }, []);

    useEffect(() => { load(); }, [load]);

    const toggleActive = async (u) => {
        if (!confirm(`¿${u.is_active ? 'Desactivar' : 'Activar'} a @${u.username}?`)) return;
        try {
            await api.post(`/api/bank/users/${u.id}/toggle-active`);
            load();
        } catch (err) {
            alert(err.response?.data?.message || 'Error.');
        }
    };

    const deleteUser = async (u) => {
        if (!confirm(`¿Eliminar definitivamente la cuenta de @${u.username}? Esta acción no se puede deshacer desde la UI.`)) return;
        if (!confirm(`⚠️ CONFIRMAR: Se eliminará @${u.username}. ¿Continuar?`)) return;
        try {
            await api.delete(`/api/bank/users/${u.id}`);
            load();
        } catch (err) {
            alert(err.response?.data?.message || 'Error.');
        }
    };

    if (error) return <p className="text-red-400 text-sm">{error}</p>;
    if (!users) return <p className="text-gray-400 text-sm">Cargando...</p>;

    return (
        <>
            <div className="space-y-2">
                {users.map((u) => (
                    <div key={u.id} className={`bg-gray-700 rounded-lg p-3 ${!u.is_active ? 'opacity-60' : ''}`}>
                        <div className="flex justify-between items-start">
                            <div>
                                <p className="font-medium">
                                    {u.name} {u.last_name || ''}
                                    {u.id === me.id && <span className="text-xs text-gray-500"> (tú)</span>}
                                </p>
                                <p className="text-xs text-gray-400">
                                    @{u.username} · #{u.account_number}
                                    {!u.is_active && <span className="text-red-400"> · inactivo</span>}
                                </p>
                            </div>
                            <p className="font-bold text-yellow-400 whitespace-nowrap">{u.balance} 🪙</p>
                        </div>
                        {u.roles.length > 0 && (
                            <div className="flex flex-wrap gap-1 mt-2">
                                {u.roles.map((r) => (
                                    <span key={r} className="text-xs bg-gray-600 px-2 py-0.5 rounded">{r}</span>
                                ))}
                            </div>
                        )}
                        <div className="grid grid-cols-2 gap-2 mt-3">
                            <button
                                onClick={() => setAdjusting(u)}
                                className="bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-300 text-xs py-2 rounded"
                            >
                                💰 Ajustar
                            </button>
                            <button
                                onClick={() => setResetting(u)}
                                className="bg-blue-500/20 hover:bg-blue-500/30 text-blue-300 text-xs py-2 rounded"
                            >
                                🔑 Resetear
                            </button>
                            {u.id !== me.id && (
                                <button
                                    onClick={() => toggleActive(u)}
                                    className={`col-span-2 text-xs py-2 rounded ${
                                        u.is_active
                                            ? 'bg-red-700/50 hover:bg-red-700 text-red-200'
                                            : 'bg-green-700/50 hover:bg-green-700 text-green-200'
                                    }`}
                                >
                                    {u.is_active ? '⛔ Desactivar' : '✅ Activar'}
                                </button>
                            )}
                            {me.roles.includes('director') && u.id !== me.id && !u.roles.includes('director') && (
                                <button
                                    onClick={() => deleteUser(u)}
                                    className="col-span-2 text-xs py-2 rounded bg-gray-800 hover:bg-gray-900 text-red-400 border border-red-800"
                                >
                                    🗑️ Eliminar cuenta
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            {adjusting && (
                <AdjustBalanceModal
                    user={adjusting}
                    onClose={() => setAdjusting(null)}
                    onSuccess={() => { setAdjusting(null); load(); }}
                />
            )}

            {resetting && (
                <ResetPasswordModal
                    user={resetting}
                    onClose={() => setResetting(null)}
                    onSuccess={() => setResetting(null)}
                />
            )}
        </>
    );
}

function ResetPasswordModal({ user, onClose, onSuccess }) {
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    const submit = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await api.post(`/api/bank/users/${user.id}/reset-password`, { password });
            onSuccess();
        } catch (err) {
            const errs = err.response?.data?.errors;
            setError(errs ? Object.values(errs).flat().join(' ') : (err.response?.data?.message || 'Error.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
            <div className="bg-gray-800 rounded-2xl p-6 w-full max-w-sm">
                <div className="flex justify-between items-center mb-4">
                    <h3 className="text-lg font-bold text-blue-400">Resetear contraseña</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-white text-2xl leading-none">×</button>
                </div>

                <p className="text-gray-400 text-sm mb-4">
                    {user.name} · @{user.username}
                </p>

                {error && (
                    <div className="bg-red-900/50 text-red-200 text-sm rounded p-2 mb-3">{error}</div>
                )}

                <form onSubmit={submit} className="space-y-3">
                    <div className="relative">
                        <input
                            type={showPassword ? 'text' : 'password'}
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Nueva contraseña (mín 8)"
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
                        className="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg py-3 disabled:opacity-50"
                    >
                        {loading ? 'Guardando...' : 'Cambiar contraseña'}
                    </button>
                </form>
            </div>
        </div>
    );
}

function AdjustBalanceModal({ user, onClose, onSuccess }) {
    const [direction, setDirection] = useState('credit');
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('Bienvenida');
    const [note, setNote] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    const predefinedReasons = [
        'Bienvenida',
        'Premio',
        'Recompensa',
        'Compra',
        'Multa',
        'Corrección',
        'Otro',
    ];

    const submit = async (e) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await api.post(`/api/bank/users/${user.id}/adjust-balance`, {
                amount: parseInt(amount, 10),
                direction,
                reason,
                note: note || null,
            });
            onSuccess();
        } catch (err) {
            const errs = err.response?.data?.errors;
            setError(errs ? Object.values(errs).flat().join(' ') : (err.response?.data?.message || 'Error.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
            <div className="bg-gray-800 rounded-2xl p-6 w-full max-w-sm">
                <div className="flex justify-between items-center mb-4">
                    <h3 className="text-lg font-bold text-yellow-400">Ajustar galeones</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-white text-2xl leading-none">×</button>
                </div>

                <p className="text-gray-400 text-sm mb-4">
                    {user.name} · @{user.username} · <span className="text-yellow-400 font-bold">{user.balance} 🪙</span>
                </p>

                {error && (
                    <div className="bg-red-900/50 text-red-200 text-sm rounded p-2 mb-3">{error}</div>
                )}

                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => setDirection('credit')}
                            className={`py-3 rounded-lg font-medium ${direction === 'credit' ? 'bg-green-600 text-white' : 'bg-gray-700 text-gray-300'}`}
                        >
                            ➕ Dar
                        </button>
                        <button
                            type="button"
                            onClick={() => setDirection('debit')}
                            className={`py-3 rounded-lg font-medium ${direction === 'debit' ? 'bg-red-600 text-white' : 'bg-gray-700 text-gray-300'}`}
                        >
                            ➖ Quitar
                        </button>
                    </div>

                    <input
                        type="number"
                        min="1"
                        value={amount}
                        onChange={(e) => setAmount(e.target.value)}
                        placeholder="Cantidad (G)"
                        className="w-full bg-gray-700 text-white rounded-lg px-4 py-3"
                        required
                    />

                    <select
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        className="w-full bg-gray-700 text-white rounded-lg px-4 py-3"
                    >
                        {predefinedReasons.map((r) => (
                            <option key={r} value={r}>{r}</option>
                        ))}
                    </select>

                    {(reason === 'Otro' || note) && (
                        <input
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            placeholder={reason === 'Otro' ? 'Motivo obligatorio' : 'Nota (opcional)'}
                            className="w-full bg-gray-700 text-white rounded-lg px-4 py-3"
                            required={reason === 'Otro'}
                        />
                    )}

                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full bg-yellow-500 hover:bg-yellow-400 text-gray-900 font-bold rounded-lg py-3 disabled:opacity-50"
                    >
                        {loading ? 'Aplicando...' : `Aplicar ${direction === 'credit' ? '+' : '-'}${amount || 0} 🪙`}
                    </button>
                </form>
            </div>
        </div>
    );
}
function RequestsList({ canApprove }) {
    const [requests, setRequests] = useState(null);
    const [error, setError] = useState(null);

    const load = useCallback(() => {
        api.get('/api/bank/account-requests')
            .then((res) => setRequests(res.data.requests))
            .catch((err) => setError(err.response?.data?.message || 'Error.'));
    }, []);

    useEffect(() => { load(); }, [load]);

    const approve = async (id) => {
        try {
            await api.post(`/api/bank/account-requests/${id}/approve`);
            load();
        } catch (err) {
            alert(err.response?.data?.message || 'Error.');
        }
    };

    const reject = async (id) => {
        const reason = prompt('Motivo del rechazo:');
        if (!reason) return;
        try {
            await api.post(`/api/bank/account-requests/${id}/reject`, { reason });
            load();
        } catch (err) {
            alert(err.response?.data?.message || 'Error.');
        }
    };

    if (error) return <p className="text-red-400 text-sm">{error}</p>;
    if (!requests) return <p className="text-gray-400 text-sm">Cargando...</p>;
    if (requests.length === 0) return <p className="text-gray-400 text-sm">No hay solicitudes pendientes.</p>;

    return (
        <div className="space-y-2">
            {requests.map((r) => (
                <div key={r.id} className="bg-gray-700 rounded-lg p-3">
                    <p className="font-medium">{r.name} {r.last_name || ''}</p>
                    <p className="text-xs text-gray-400">
                        @{r.username} · {r.requested_by ? `pedido por @${r.requested_by}` : 'autorregistro'}
                    </p>
                    {canApprove && (
                        <div className="flex gap-2 mt-2">
                            <button onClick={() => approve(r.id)} className="flex-1 bg-green-700 hover:bg-green-600 py-2 rounded text-sm">Aprobar</button>
                            <button onClick={() => reject(r.id)} className="flex-1 bg-red-700 hover:bg-red-600 py-2 rounded text-sm">Rechazar</button>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

function CreateUserForm() {
    const [form, setForm] = useState({ username: '', name: '', last_name: '', password: '', role: 'estudiante' });
    const [showPassword, setShowPassword] = useState(false);
    const [msg, setMsg] = useState(null);
    const [error, setError] = useState(null);

    const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

    const submit = async (e) => {
        e.preventDefault();
        setMsg(null);
        setError(null);
        try {
            const res = await api.post('/api/bank/users', form);
            setMsg(`Usuario @${res.data.user.username} creado.`);
            setForm({ username: '', name: '', last_name: '', password: '', role: 'estudiante' });
        } catch (err) {
            const errs = err.response?.data?.errors;
            setError(errs ? Object.values(errs).flat().join(' ') : (err.response?.data?.message || 'Error.'));
        }
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            {msg && <div className="bg-green-900/50 text-green-200 text-sm rounded p-2">{msg}</div>}
            {error && <div className="bg-red-900/50 text-red-200 text-sm rounded p-2">{error}</div>}

            <input value={form.username} onChange={set('username')} placeholder="username" className="w-full bg-gray-700 text-white rounded-lg px-4 py-3" required />
            <input value={form.name} onChange={set('name')} placeholder="Nombre" className="w-full bg-gray-700 text-white rounded-lg px-4 py-3" required />
            <input value={form.last_name} onChange={set('last_name')} placeholder="Apellido (opcional)" className="w-full bg-gray-700 text-white rounded-lg px-4 py-3" />

            <div className="relative">
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
                    aria-label={showPassword ? 'Ocultar' : 'Mostrar'}
                >
                    {showPassword ? '🙈' : '👁️'}
                </button>
            </div>

            <select value={form.role} onChange={set('role')} className="w-full bg-gray-700 text-white rounded-lg px-4 py-3">
                <option value="estudiante">Estudiante</option>
                <option value="banquero">Banquero</option>
                <option value="jefe_casa">Jefe de Casa</option>
                <option value="moderador">Moderador</option>
                <option value="vice">Vice</option>
                <option value="director">Director</option>
            </select>

            <button type="submit" className="w-full bg-yellow-500 hover:bg-yellow-400 text-gray-900 font-bold rounded-lg py-3">
                Crear usuario
            </button>
        </form>
    );
}

// === Historial de transacciones (saldo) ===
function MyTransactions() {
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);

    const load = useCallback(() => {
        api.get('/api/transactions/mine')
            .then((res) => setData(res.data.transactions))
            .catch((err) => setError(err.response?.data?.message || 'Error.'));
    }, []);

    useEffect(() => { load(); }, [load]);

    const reverse = async (id) => {
        const reason = prompt('Motivo de la reversión:');
        if (!reason) return;
        try {
            await api.post(`/api/bank/transactions/${id}/reverse`, { reason });
            load();
        } catch (err) {
            alert(err.response?.data?.message || 'Error.');
        }
    };

    if (error) return <p className="text-red-400 text-sm">{error}</p>;
    if (!data) return <p className="text-gray-400 text-sm">Cargando...</p>;
    if (data.length === 0) return <p className="text-gray-400 text-sm">Sin movimientos.</p>;

    return (
        <div className="space-y-2">
            {data.map((t) => (
                <div key={t.id} className="bg-gray-700 rounded-lg p-3">
                    <div className="flex justify-between items-start">
                        <div className="flex-1">
                            <p className="text-sm text-gray-200">
                                {t.type === 'reversal' && <span className="text-orange-400 font-bold">↩ </span>}
                                {t.reason}
                            </p>
                            {t.note && <p className="text-xs text-gray-400 mt-1">"{t.note}"</p>}
                            <p className="text-xs text-gray-500 mt-1">
                                {new Date(t.created_at).toLocaleString('es-ES')}
                                {t.created_by && <> · por @{t.created_by}</>}
                            </p>
                        </div>
                        <p className={`font-bold whitespace-nowrap ml-2 ${t.amount >= 0 ? 'text-green-400' : 'text-red-400'}`}>
                            {t.amount >= 0 ? '+' : ''}{t.amount} 🪙
                        </p>
                    </div>
                    {t.can_reverse && (
                        <button
                            onClick={() => reverse(t.id)}
                            className="mt-2 text-xs bg-orange-700 hover:bg-orange-600 px-3 py-1 rounded"
                        >
                            ↩ Revertir
                        </button>
                    )}
                </div>
            ))}
        </div>
    );
}

function NotificationBell() {
    const [open, setOpen] = useState(false);
    const [count, setCount] = useState(0);
    const [notifications, setNotifications] = useState(null);

    const loadCount = useCallback(() => {
        api.get('/api/notifications/unread-count')
            .then((res) => setCount(res.data.count))
            .catch(() => {});
    }, []);

    const loadList = useCallback(() => {
        api.get('/api/notifications')
            .then((res) => setNotifications(res.data.notifications))
            .catch(() => {});
    }, []);

    useEffect(() => {
        loadCount();
        const interval = setInterval(loadCount, 30000);
        return () => clearInterval(interval);
    }, [loadCount]);

    const toggleOpen = () => {
        if (!open) loadList();
        setOpen(!open);
    };

    const markAll = async () => {
        await api.post('/api/notifications/read-all');
        loadCount();
        loadList();
    };

    const markOne = async (id) => {
        await api.post(`/api/notifications/${id}/read`);
        loadCount();
        loadList();
    };

    return (
        <div className="relative">
            <button onClick={toggleOpen} className="relative bg-gray-800 hover:bg-gray-700 px-3 py-2 rounded-lg text-xl">
                🔔
                {count > 0 && (
                    <span className="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        {count > 9 ? '9+' : count}
                    </span>
                )}
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                    <div
                        className="fixed left-2 right-2 top-16 z-50 bg-gray-800 rounded-2xl shadow-xl max-h-[70vh] overflow-y-auto sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-80 sm:max-h-96"
                    >
                        <div className="flex justify-between items-center p-3 border-b border-gray-700 sticky top-0 bg-gray-800">
                            <h3 className="font-bold text-yellow-400">Notificaciones</h3>
                            <button onClick={markAll} className="text-xs text-gray-400 hover:text-white">
                                Marcar todas leídas
                            </button>
                        </div>

                        {!notifications && <p className="p-4 text-gray-400 text-sm">Cargando...</p>}
                        {notifications && notifications.length === 0 && (
                            <p className="p-4 text-gray-400 text-sm text-center">Sin notificaciones.</p>
                        )}

                        {notifications && notifications.map((n) => (
                            <div
                                key={n.id}
                                onClick={() => !n.is_read && markOne(n.id)}
                                className={`p-3 border-b border-gray-700 cursor-pointer hover:bg-gray-700/50 ${!n.is_read ? 'bg-gray-700/30' : ''}`}
                            >
                                <p className={`text-sm ${!n.is_read ? 'font-bold text-white' : 'text-gray-300'}`}>
                                    {n.title}
                                </p>
                                <p className="text-xs text-gray-400 mt-1 break-words">{n.message}</p>
                                <p className="text-xs text-gray-500 mt-1">
                                    {new Date(n.created_at).toLocaleString('es-ES')}
                                </p>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

function AuditLog() {
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        api.get('/api/bank/transactions/all')
            .then((res) => setData(res.data.transactions))
            .catch((err) => setError(err.response?.data?.message || 'Error.'));
    }, []);

    if (error) return <p className="text-red-400 text-sm">{error}</p>;
    if (!data) return <p className="text-gray-400 text-sm">Cargando...</p>;
    if (data.length === 0) return <p className="text-gray-400 text-sm">Sin movimientos.</p>;

    return (
        <div className="space-y-2">
            {data.map((t) => (
                <div key={t.id} className="bg-gray-700 rounded-lg p-3">
                    <div className="flex justify-between items-start">
                        <div className="flex-1">
                            <p className="text-sm text-gray-200">
                                {t.type === 'reversal' && <span className="text-orange-400 font-bold">↩ </span>}
                                <span className="font-medium">@{t.user_username}</span>
                                {' · '}
                                {t.reason}
                            </p>
                            {t.note && <p className="text-xs text-gray-400 mt-1">"{t.note}"</p>}
                            <p className="text-xs text-gray-500 mt-1">
                                {new Date(t.created_at).toLocaleString('es-ES')}
                                {t.created_by && <> · por @{t.created_by}</>}
                            </p>
                        </div>
                        <p className={`font-bold whitespace-nowrap ml-2 ${t.amount >= 0 ? 'text-green-400' : 'text-red-400'}`}>
                            {t.amount >= 0 ? '+' : ''}{t.amount} 🪙
                        </p>
                    </div>
                </div>
            ))}
        </div>
    );
}