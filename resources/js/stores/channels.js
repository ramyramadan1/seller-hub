import { defineStore } from 'pinia';
import api from '../api';

export const useChannelsStore = defineStore('channels', {
    state: () => ({ items: [], availableDrivers: [], loading: false }),
    actions: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await api.get('/channels');
                this.items = data.data;
                this.availableDrivers = data.available_drivers;
            } finally {
                this.loading = false;
            }
        },
        async connect(payload) {
            const { data } = await api.post('/channels', payload);
            this.items.push(data);

            return data;
        },
        async test(id) {
            const { data } = await api.post(`/channels/${id}/test`);
            this.replace(data);

            return data;
        },
        async remove(id) {
            await api.delete(`/channels/${id}`);
            this.items = this.items.filter((c) => c.id !== id);
        },
        replace(channel) {
            const i = this.items.findIndex((c) => c.id === channel.id);
            if (i >= 0) this.items[i] = channel;
        },
    },
});
