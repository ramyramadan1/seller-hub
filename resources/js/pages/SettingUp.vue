<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useUiStore } from '../stores/ui';
import { errorMessage } from '../api';
import { useT } from '../i18n';

const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();
const t = useT();
const failed = computed(() => auth.tenant?.status === 'failed');
let timer = null;

async function poll() {
    try {
        const status = await auth.fetchStatus();
        if (status === 'active') {
            stop();
            router.replace('/');
        } else if (status === 'failed') {
            stop();
        }
    } catch {
        // 401 is handled by the Axios hook; a transient network error just waits for the next tick
    }
}

function start() {
    stop();
    poll();
    timer = setInterval(poll, 2000);
}

function stop() {
    if (timer) clearInterval(timer);
    timer = null;
}

async function retry() {
    try {
        await auth.retryProvisioning();
        start();
    } catch (e) {
        ui.toast(errorMessage(e));
    }
}

onMounted(start);
onBeforeUnmount(stop);
</script>

<template>
    <div class="container py-5 text-center" style="max-width: 480px">
        <template v-if="!failed">
            <div class="spinner-border text-primary mb-4" role="status"></div>
            <h1 class="h4">{{ t('setup.title') }}</h1>
            <p class="text-muted">{{ t('setup.body') }}</p>
        </template>
        <template v-else>
            <h1 class="h4 text-danger">{{ t('setup.failed_title') }}</h1>
            <p class="text-muted">{{ t('setup.failed_body') }}</p>
            <button v-if="auth.isOwner" class="btn btn-primary" @click="retry">{{ t('setup.retry') }}</button>
        </template>
    </div>
</template>
