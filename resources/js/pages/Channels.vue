<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useChannelsStore } from '../stores/channels';
import { useUiStore } from '../stores/ui';
import { useAuthStore } from '../stores/auth';
import { errorMessage } from '../api';
import { useT } from '../i18n';

const channels = useChannelsStore();
const ui = useUiStore();
const auth = useAuthStore();
const t = useT();

const isOwner = computed(() => auth.user?.role === 'owner');
const showForm = ref(false);
const saving = ref(false);
const busyId = ref(null);
const formError = ref('');
const empty = () => ({ driver: 'woocommerce', name: '', store_url: '', consumer_key: '', consumer_secret: '' });
const form = reactive(empty());

// Platforms the plan covers; the ones the API does not offer yet are shown as "coming soon".
const platforms = ['woocommerce', 'shopify', 'salla', 'zid', 'amazon'];
const badge = { connected: 'text-bg-success', pending: 'text-bg-secondary', needs_reauth: 'text-bg-warning', error: 'text-bg-danger' };

onMounted(() => channels.fetch().catch((e) => ui.toast(errorMessage(e))));

const connectedByDriver = (driver) => channels.items.filter((c) => c.driver === driver);
const isAvailable = (driver) => channels.availableDrivers.includes(driver);

function openForm(driver) {
    Object.assign(form, empty(), { driver });
    formError.value = '';
    showForm.value = true;
}

async function save() {
    saving.value = true;
    formError.value = '';
    try {
        await channels.connect({ ...form });
        showForm.value = false;
        ui.toast(t('channels.connected_ok'));
    } catch (e) {
        formError.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}

async function test(channel) {
    busyId.value = channel.id;
    try {
        const updated = await channels.test(channel.id);
        ui.toast(updated.status === 'connected' ? t('channels.test_ok') : updated.last_error || t('channels.test_failed'));
    } catch (e) {
        ui.toast(errorMessage(e));
    } finally {
        busyId.value = null;
    }
}

async function remove(channel) {
    if (!window.confirm(t('channels.confirm_remove'))) return;
    busyId.value = channel.id;
    try {
        await channels.remove(channel.id);
    } catch (e) {
        ui.toast(errorMessage(e));
    } finally {
        busyId.value = null;
    }
}
</script>

<template>
    <div class="d-flex align-items-center mb-3">
        <h1 class="h4 m-0 me-auto">{{ t('channels.title') }}</h1>
    </div>
    <p class="text-muted">{{ t('channels.help') }}</p>

    <div v-if="channels.loading" class="text-muted py-4">{{ t('common.loading') }}</div>

    <div v-else class="row g-3">
        <div v-for="driver in platforms" :key="driver" class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <h2 class="h5 m-0 me-auto">{{ t(`platform.${driver}`) }}</h2>
                        <button v-if="isAvailable(driver) && isOwner" class="btn btn-primary btn-sm" @click="openForm(driver)">
                            {{ t('channels.connect') }}
                        </button>
                        <span v-else-if="!isAvailable(driver)" class="badge text-bg-light border">{{ t('nav.soon') }}</span>
                    </div>

                    <p v-if="isAvailable(driver) && !connectedByDriver(driver).length" class="text-muted small m-0">
                        {{ t('channels.none') }}
                    </p>

                    <ul class="list-group list-group-flush">
                        <li v-for="c in connectedByDriver(driver)" :key="c.id" class="list-group-item px-0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <div class="me-auto min-w-0">
                                    <div class="fw-semibold">{{ c.name }}</div>
                                    <div class="text-muted small text-truncate">{{ c.store_url }}</div>
                                </div>
                                <span class="badge" :class="badge[c.status] || 'text-bg-secondary'">{{ t(`channels.status.${c.status}`) }}</span>
                                <button class="btn btn-outline-secondary btn-sm" :disabled="busyId === c.id" @click="test(c)">{{ t('channels.test') }}</button>
                                <button v-if="isOwner" class="btn btn-outline-danger btn-sm" :disabled="busyId === c.id" @click="remove(c)">
                                    {{ t('channels.disconnect') }}
                                </button>
                            </div>
                            <div v-if="c.last_error && c.status !== 'connected'" class="text-danger small mt-1">{{ c.last_error }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <template v-if="showForm">
        <div class="modal-backdrop-lite" @click="showForm = false"></div>
        <div class="modal d-block" tabindex="-1" role="dialog" @click.self="showForm = false">
            <div class="modal-dialog"><form class="modal-content" @submit.prevent="save">
                <div class="modal-header">
                    <h2 class="modal-title h5">{{ t('channels.connect') }} · {{ t(`platform.${form.driver}`) }}</h2>
                    <button type="button" class="btn-close" @click="showForm = false"></button>
                </div>
                <div class="modal-body row g-3">
                    <div v-if="formError" class="col-12"><div class="alert alert-danger m-0">{{ formError }}</div></div>
                    <div class="col-12"><label class="form-label">{{ t('channels.name') }}</label>
                        <input v-model="form.name" class="form-control" required maxlength="100" /></div>
                    <div class="col-12"><label class="form-label">{{ t('channels.store_url') }}</label>
                        <input v-model="form.store_url" type="url" class="form-control" placeholder="https://myshop.com" required /></div>
                    <div class="col-12"><label class="form-label">{{ t('channels.consumer_key') }}</label>
                        <input v-model="form.consumer_key" class="form-control" autocomplete="off" required /></div>
                    <div class="col-12"><label class="form-label">{{ t('channels.consumer_secret') }}</label>
                        <input v-model="form.consumer_secret" type="password" class="form-control" autocomplete="new-password" required /></div>
                    <div class="col-12 text-muted small">{{ t('channels.woo_help') }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" @click="showForm = false">{{ t('common.cancel') }}</button>
                    <button class="btn btn-primary" :disabled="saving">{{ saving ? t('channels.testing') : t('channels.connect') }}</button>
                </div>
            </form></div>
        </div>
    </template>
</template>
