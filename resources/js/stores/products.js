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
        async update(id, payload) {
            const { data } = await api.put(`/products/${id}`, payload);
            const i = this.items.findIndex((p) => p.id === id);
            if (i >= 0) this.items[i] = data;

            return data;
        },
        async remove(id) {
            await api.delete(`/products/${id}`);
            this.items = this.items.filter((p) => p.id !== id);
            this.total -= 1;
        },
        /** Queues one job per product and channel; the list is reloaded to show pending/synced/failed. */
        async publish(productIds, channelIds) {
            const { data } = await api.post('/products/publish', { product_ids: productIds, channel_ids: channelIds });

            return data;
        },
    },
});
