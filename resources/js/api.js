import axios from 'axios';

export const TOKEN_KEY = 'seller_hub_token';

const api = axios.create({ baseURL: '/api', timeout: 20000, headers: { Accept: 'application/json' } });

let hooks = {};
/** The app wires router/store reactions here to avoid circular imports. */
export function installApiHooks(h) {
    hooks = h;
}

api.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (token) config.headers.Authorization = `Bearer ${token}`;

    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const data = error.response?.data;

        if (status === 401) hooks.onUnauthorized?.();
        else if (status === 409 && data?.status) hooks.onStoreNotReady?.(data.status);

        return Promise.reject(error);
    },
);

/** Turn an Axios/Laravel error into one readable sentence. */
export function errorMessage(error) {
    const data = error.response?.data;
    if (data?.errors) return Object.values(data.errors).flat().join(' ');

    return data?.message || error.message || 'Something went wrong.';
}

export default api;
