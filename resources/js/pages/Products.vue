<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useProductsStore } from '../stores/products';
import { useUiStore } from '../stores/ui';
import { useAuthStore } from '../stores/auth';
import { errorMessage } from '../api';
import { useT } from '../i18n';

const products = useProductsStore();
const ui = useUiStore();
const auth = useAuthStore();
const t = useT();

const search = ref('');
const showForm = ref(false);
const saving = ref(false);
const empty = () => ({ title: '', sku: '', price: '', cost_price: '', stock: 0 });
const form = reactive(empty());

const load = () => products.fetch(search.value).catch((e) => ui.toast(errorMessage(e)));
onMounted(load);

function openForm() {
    Object.assign(form, empty());
    showForm.value = true;
}

async function save() {
    saving.value = true;
    try {
        await products.create({
            title: form.title,
            sku: form.sku || null,
            price: form.price,
            cost_price: form.cost_price === '' ? null : form.cost_price,
            stock: form.stock || 0,
        });
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
        <button class="btn btn-primary" @click="openForm">{{ t('products.add') }}</button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ t('products.sku') }}</th><th>{{ t('products.name') }}</th>
                        <th class="text-end">{{ t('products.price') }}</th><th class="text-end">{{ t('products.cost') }}</th>
                        <th class="text-end">{{ t('products.stock') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="products.loading"><td colspan="5" class="text-center text-muted py-4">{{ t('common.loading') }}</td></tr>
                    <tr v-else-if="!products.items.length"><td colspan="5" class="text-center text-muted py-4">{{ t('products.empty') }}</td></tr>
                    <tr v-for="p in products.items" :key="p.id">
                        <td class="text-muted">{{ p.sku || '—' }}</td>
                        <td>{{ p.title }}</td>
                        <td class="text-end">{{ p.price }} {{ auth.tenant?.currency }}</td>
                        <td class="text-end">
                            <span v-if="p.cost_price === null" class="badge text-bg-warning">{{ t('products.missing_cost') }}</span>
                            <span v-else>{{ p.cost_price }}</span>
                        </td>
                        <td class="text-end">{{ p.stock }}</td>
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
                    <h2 class="modal-title h5">{{ t('products.add') }}</h2>
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
</template>
