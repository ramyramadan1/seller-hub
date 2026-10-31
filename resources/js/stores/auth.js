import { defineStore } from 'pinia';
import api, { TOKEN_KEY } from '../api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem(TOKEN_KEY),
        user: null,
        tenant: null,
        credits: 0,
    }),
    getters: {
        isAuthed: (state) => !!state.token,
        isActive: (state) => state.tenant?.status === 'active',
        isOwner: (state) => state.user?.role === 'owner',
    },
    actions: {
        setSession({ token, user, tenant }) {
            if (token) {
                this.token = token;
                localStorage.setItem(TOKEN_KEY, token);
            }
            this.user = user;
            this.tenant = tenant;
        },
        clear() {
            this.token = null;
            this.user = null;
            this.tenant = null;
            this.credits = 0;
            localStorage.removeItem(TOKEN_KEY);
        },
        async register(form) {
            const { data } = await api.post('/register', form);
            this.setSession(data);
        },
        async login(form) {
            const { data } = await api.post('/login', form);
            this.setSession(data);
        },
        async fetchMe() {
            const { data } = await api.get('/me');
            this.user = data.user;
            this.tenant = data.tenant;
            this.credits = data.credits_balance;
        },
        async fetchStatus() {
            const { data } = await api.get('/tenant/status');
            if (this.tenant) this.tenant.status = data.status;

            return data.status;
        },
        async retryProvisioning() {
            const { data } = await api.post('/tenant/retry');
            if (this.tenant) this.tenant.status = data.status;
        },
        async logout() {
            try {
                await api.post('/logout');
            } catch {
                // token already invalid: nothing to revoke
            }
            this.clear();
        },
    },
});
