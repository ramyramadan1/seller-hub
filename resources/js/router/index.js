import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    { path: '/login', component: () => import('../pages/Login.vue'), meta: { guest: true } },
    { path: '/register', component: () => import('../pages/Register.vue'), meta: { guest: true } },
    { path: '/setting-up', component: () => import('../pages/SettingUp.vue'), meta: { auth: true } },
    {
        path: '/',
        component: () => import('../layouts/AppLayout.vue'),
        meta: { auth: true, active: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('../pages/Dashboard.vue') },
            { path: 'channels', name: 'channels', component: () => import('../pages/Channels.vue') },
            { path: 'products', name: 'products', component: () => import('../pages/Products.vue') },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({ history: createWebHistory(), routes });

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (auth.token && !auth.user) {
        try {
            await auth.fetchMe();
        } catch {
            auth.clear();
        }
    }

    if (to.meta.guest && auth.isAuthed) return auth.isActive ? '/' : '/setting-up';
    if (to.meta.auth && !auth.isAuthed) return '/login';
    if (to.meta.active && !auth.isActive) return '/setting-up';
    if (to.path === '/setting-up' && auth.isActive) return '/';
});

export default router;
