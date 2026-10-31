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
const currencies = ['SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'EGP', 'USD', 'EUR'];

const form = reactive({
    store_name: '', currency: 'SAR', locale: ui.locale,
    name: '', email: '', password: '', password_confirmation: '',
});
const loading = ref(false);

async function submit() {
    loading.value = true;
    form.locale = ui.locale;
    try {
        await auth.register(form);
        router.push(auth.isActive ? '/' : '/setting-up');
    } catch (e) {
        ui.toast(errorMessage(e));
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="container py-5" style="max-width: 520px">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 m-0">{{ t('app.name') }}</h1>
            <LocaleSwitch />
        </div>
        <form class="card card-body shadow-sm" @submit.prevent="submit">
            <h2 class="h5 mb-3">{{ t('auth.register') }}</h2>
            <div class="row g-3">
                <div class="col-sm-8">
                    <label class="form-label">{{ t('auth.store_name') }}</label>
                    <input v-model="form.store_name" class="form-control" required maxlength="120" />
                </div>
                <div class="col-sm-4">
                    <label class="form-label">{{ t('auth.currency') }}</label>
                    <select v-model="form.currency" class="form-select">
                        <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">{{ t('auth.name') }}</label>
                    <input v-model="form.name" class="form-control" required maxlength="120" autocomplete="name" />
                </div>
                <div class="col-12">
                    <label class="form-label">{{ t('auth.email') }}</label>
                    <input v-model="form.email" type="email" class="form-control" required autocomplete="email" />
                </div>
                <div class="col-sm-6">
                    <label class="form-label">{{ t('auth.password') }}</label>
                    <input v-model="form.password" type="password" class="form-control" required minlength="8" autocomplete="new-password" />
                </div>
                <div class="col-sm-6">
                    <label class="form-label">{{ t('auth.password_confirm') }}</label>
                    <input v-model="form.password_confirmation" type="password" class="form-control" required minlength="8" autocomplete="new-password" />
                </div>
            </div>
            <button class="btn btn-primary mt-4" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>{{ t('auth.register') }}
            </button>
        </form>
        <p class="text-center mt-3"><RouterLink to="/login">{{ t('auth.have_account') }}</RouterLink></p>
    </div>
</template>
