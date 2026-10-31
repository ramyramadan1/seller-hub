<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useT } from '../i18n';
import LocaleSwitch from '../components/LocaleSwitch.vue';

const auth = useAuthStore();
const router = useRouter();
const t = useT();
const open = ref(false);

const soon = ['nav.channels', 'nav.ai', 'nav.orders', 'nav.analytics', 'nav.settings'];

async function logout() {
    await auth.logout();
    router.push('/login');
}
</script>

<template>
    <div class="d-flex min-vh-100">
        <nav class="sidebar text-white p-3 d-flex flex-column" :class="{ open }">
            <div class="fs-5 fw-bold mb-4 px-2">{{ t('app.name') }}</div>
            <ul class="nav flex-column gap-1" @click="open = false">
                <li class="nav-item"><RouterLink class="nav-link" to="/">{{ t('nav.dashboard') }}</RouterLink></li>
                <li class="nav-item"><RouterLink class="nav-link" to="/products">{{ t('nav.products') }}</RouterLink></li>
                <li v-for="key in soon" :key="key" class="nav-item">
                    <span class="nav-link disabled d-flex justify-content-between" :title="t('nav.soon')">{{ t(key) }}</span>
                </li>
            </ul>
        </nav>

        <div class="flex-grow-1 d-flex flex-column min-w-0">
            <header class="bg-white border-bottom px-3 py-2 d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary btn-sm d-md-none" @click="open = !open" aria-label="Menu">☰</button>
                <div class="fw-semibold">{{ auth.tenant?.name }}</div>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <LocaleSwitch />
                    <span class="text-muted small d-none d-sm-inline">{{ auth.user?.name }}</span>
                    <button class="btn btn-outline-secondary btn-sm" @click="logout">{{ t('auth.logout') }}</button>
                </div>
            </header>
            <main class="p-3 p-md-4 flex-grow-1">
                <RouterView />
            </main>
        </div>
    </div>
</template>
