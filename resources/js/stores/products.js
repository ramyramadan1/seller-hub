import { defineStore } from 'pinia';
import api from '../api';

export const useProductsStore = defineStore('products', {
    state: () => ({ items: [], total: 0, loading: false }),
    actions: {
        async fetch(search = '') {
            this.loading = true;
            try {
                const { data } = await api.get('/products', { params: { search, per_page: 50 } });
                this.items = data.data;
                this.total = data.total;
            } finally {
                this.loading = false;
            }
        },
        async create(payload) {
            const { data } = await api.post('/products', payload);
            this.items.unshift(data);
            this.total += 1;

            return data;
        },
    },
});
