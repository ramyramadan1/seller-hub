<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useProductsStore } from '../stores/products';
import { useChannelsStore } from '../stores/channels';
import { useUiStore } from '../stores/ui';
import { useAuthStore } from '../stores/auth';
import { errorMessage } from '../api';
import { useT } from '../i18n';

const products = useProductsStore();
const channels = useChannelsStore();
const ui = useUiStore();
const auth = useAuthStore();
const t = useT();

const search = ref('');
const showForm = ref(false);
const saving = ref(false);
const empty = () => ({ title: '', sku: '', price: '', cost_price: '', stock: 0 });
const form = reactive(empty());

const editingId = ref(null);
const selected = ref([]);
const showPublish = ref(false);
const publishTo = ref([]);
const publishing = ref(false);
let poll = null;

const connected = computed(() => channels.items.filter((c) => c.status === 'connected'));
const channelName = (id) => channels.items.find((c) => c.id === id)?.name || `#${id}`;
const syncBadge = { synced: 'text-bg-success', pending: 'text-bg-secondary', failed: 'text-bg-danger' };
const allSelected = computed(() => products.items.length > 0 && selected.value.length === products.items.length);

const load = () => products.fetch(search.value).catch((e) => ui.toast(errorMessage(e)));

// While anything is pending, refresh every 3 seconds so the owner sees results without reloading.
function schedulePoll() {
    clearTimeout(poll);
    if (products.items.some((p) => p.channels?.some((c) => c.sync_status === 'pending'))) {
        poll = setTimeout(async () => { await load(); schedulePoll(); }, 3000);
    }
}

onMounted(async () => {
    await Promise.all([load(), channels.fetch().catch(() => {})]);
    schedulePoll();
});
onBeforeUnmount(() => clearTimeout(poll));

function toggleAll() {
    selected.value = allSelected.value ? [] : products.items.map((p) => p.id);
}

function openForm(product = null) {
    editingId.value = product?.id ?? null;
    Object.assign(form, product
        ? { title: product.title, sku: product.sku || '', price: product.price, cost_price: product.cost_price ?? '', stock: product.stock }
        : empty());
    showForm.value = true;
}

async function remove(p) {
    if (!window.confirm(t('products.confirm_delete'))) return;
    try {
        await products.remove(p.id);
        selected.value = selected.value.filter((id) => id !== p.id);
    } catch (e) {
        ui.toast(errorMessage(e));
    }
}

function openPublish(ids) {
    if (ids) selected.value = ids;
    publishTo.value = connected.value.map((c) => c.id);
    showPublish.value = true;
}

async function doPublish() {
    publishing.value = true;
    try {
        await products.publish(selected.value, publishTo.value);
        showPublish.value = false;
        ui.toast(t('products.publish_queued'));
        await load();
        schedulePoll();
    } catch (e) {
        ui.toast(errorMessage(e));
    } finally {
        publishing.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        const payload = {
            title: form.title,
            sku: form.sku || null,
            price: form.price,
            cost_price: form.cost_price === '' ? null : form.cost_price,
            stock: form.stock || 0,
        };
        if (editingId.value) await products.update(editingId.value, payload);
        else await products.create(payload);
        showForm.value = false;
    } catch (e) {
        ui.toast(errorMessage(e));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h1 class="h4 m-0 me-auto">{{ t('products.title') }}</h1>
        <input v-model="search" class="form-control" style="max-width: 240px" :placeholder="t('products.search')" @keyup.enter="load" />
        <button class="btn btn-outline-primary" :disabled="!selected.length" @click="openPublish()">
            {{ t('products.publish') }}<span v-if="selected.length"> ({{ selected.length }})</span>
        </button>
        <button class="btn btn-primary" @click="openForm()">{{ t('products.add') }}</button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 2rem"><input type="checkbox" class="form-check-input" :checked="allSelected" @change="toggleAll" /></th>
                        <th>{{ t('products.sku') }}</th><th>{{ t('products.name') }}</th>
                        <th class="text-end">{{ t('products.price') }}</th><th class="text-end">{{ t('products.cost') }}</th>
                        <th class="text-end">{{ t('products.stock') }}</th>
                        <th>{{ t('products.channels') }}</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="products.loading"><td colspan="8" class="text-center text-muted py-4">{{ t('common.loading') }}</td></tr>
                    <tr v-else-if="!products.items.length"><td colspan="8" class="text-center text-muted py-4">{{ t('products.empty') }}</td></tr>
                    <tr v-for="p in products.items" :key="p.id">
                        <td><input v-model="selected" type="checkbox" class="form-check-input" :value="p.id" /></td>
                        <td class="text-muted">{{ p.sku || '—' }}</td>
                        <td>{{ p.title }}</td>
                        <td class="text-end">{{ p.price }} {{ auth.tenant?.currency }}</td>
                        <td class="text-end">
                            <span v-if="p.cost_price === null" class="badge text-bg-warning">{{ t('products.missing_cost') }}</span>
                            <span v-else>{{ p.cost_price }}</span>
                        </td>
                        <td class="text-end">{{ p.stock }}</td>
                        <td>
                            <span v-for="l in p.channels" :key="l.channel_id" class="badge me-1" :class="syncBadge[l.sync_status]"
                                  :title="l.last_error || ''">{{ channelName(l.channel_id) }} · {{ t(`products.sync.${l.sync_status}`) }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-secondary me-1" @click="openForm(p)">{{ t('products.edit') }}</button>
                            <button class="btn btn-sm btn-outline-danger" @click="remove(p)">{{ t('products.delete') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <template v-if="showForm">
        <div class="modal-backdrop-lite" @click="showForm = false"></div>
        <div class="modal d-block" tabindex="-1" role="dialog" @click.self="showForm = false">
            <div class="modal-dialog"><form class="modal-content" @submit.prevent="save">
                <div class="modal-header">
                    <h2 class="modal-title h5">{{ editingId ? t('products.edit') : t('products.add') }}</h2>
                    <button type="button" class="btn-close" @click="showForm = false"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12"><label class="form-label">{{ t('products.name') }}</label>
                        <input v-model="form.title" class="form-control" required maxlength="255" /></div>
                    <div class="col-sm-6"><label class="form-label">{{ t('products.sku') }}</label>
                        <input v-model="form.sku" class="form-control" maxlength="100" /></div>
                    <div class="col-sm-6"><label class="form-label">{{ t('products.stock') }}</label>
                        <input v-model.number="form.stock" type="number" min="0" class="form-control" /></div>
                    <div class="col-sm-6"><label class="form-label">{{ t('products.price') }}</label>
                        <input v-model="form.price" type="number" step="0.01" min="0" class="form-control" required /></div>
                    <div class="col-sm-6"><label class="form-label">{{ t('products.cost') }}</label>
                        <input v-model="form.cost_price" type="number" step="0.01" min="0" class="form-control" /></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" @click="showForm = false">{{ t('common.cancel') }}</button>
                    <button class="btn btn-primary" :disabled="saving">{{ t('products.save') }}</button>
                </div>
            </form></div>
        </div>
    </template>

    <template v-if="showPublish">
        <div class="modal-backdrop-lite" @click="showPublish = false"></div>
        <div class="modal d-block" tabindex="-1" role="dialog" @click.self="showPublish = false">
            <div class="modal-dialog"><form class="modal-content" @submit.prevent="doPublish">
                <div class="modal-header">
                    <h2 class="modal-title h5">{{ t('products.publish') }} ({{ selected.length }})</h2>
                    <button type="button" class="btn-close" @click="showPublish = false"></button>
                </div>
                <div class="modal-body">
                    <p v-if="!connected.length" class="text-muted m-0">{{ t('products.no_channels') }}</p>
                    <div v-for="c in connected" :key="c.id" class="form-check">
                        <input :id="`pc${c.id}`" v-model="publishTo" class="form-check-input" type="checkbox" :value="c.id" />
                        <label class="form-check-label" :for="`pc${c.id}`">{{ c.name }} <span class="text-muted small">({{ t(`platform.${c.driver}`) }})</span></label>
                    </div>
                    <p class="text-muted small mt-3 mb-0">{{ t('products.publish_help') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" @click="showPublish = false">{{ t('common.cancel') }}</button>
                    <button class="btn btn-primary" :disabled="publishing || !publishTo.length">{{ t('products.publish') }}</button>
                </div>
            </form></div>
        </div>
    </template>
</template>
