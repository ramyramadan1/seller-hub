<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useUiStore } from '../stores/ui';
import { errorMessage } from '../api';
import { useT } from '../i18n';
import LocaleSwitch from '../components/LocaleSwitch.vue';

const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();
const t = useT();
const form = reactive({ email: '', password: '' });
const loading = ref(false);

async function submit() {
    loading.value = true;
    try {
        await auth.login(form);
        router.push(auth.isActive ? '/' : '/setting-up');
    } catch (e) {
        ui.toast(errorMessage(e));
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="container py-5" style="max-width: 440px">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 m-0">{{ t('app.name') }}</h1>
            <LocaleSwitch />
        </div>
        <form class="card card-body shadow-sm" @submit.prevent="submit">
            <h2 class="h5 mb-3">{{ t('auth.login') }}</h2>
            <label class="form-label">{{ t('auth.email') }}</label>
            <input v-model="form.email" type="email" class="form-control mb-3" required autocomplete="email" />
            <label class="form-label">{{ t('auth.password') }}</label>
            <input v-model="form.password" type="password" class="form-control mb-3" required autocomplete="current-password" />
            <button class="btn btn-primary" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>{{ t('auth.login') }}
            </button>
        </form>
        <p class="text-center mt-3"><RouterLink to="/register">{{ t('auth.no_account') }}</RouterLink></p>
    </div>
</template>
