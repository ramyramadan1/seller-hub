import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { installApiHooks } from './api';
import { useAuthStore } from './stores/auth';
import { useUiStore } from './stores/ui';
import '../css/app.css';

const app = createApp(App);
app.use(createPinia());
app.use(router);

const ui = useUiStore();
ui.applyLocale();

const auth = useAuthStore();
installApiHooks({
    onUnauthorized: () => {
        auth.clear();
        if (router.currentRoute.value.path !== '/login') router.push('/login');
    },
    onStoreNotReady: (status) => {
        if (auth.tenant) auth.tenant.status = status;
        router.push('/setting-up');
    },
});

app.mount('#app');
