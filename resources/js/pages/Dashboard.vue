<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useProductsStore } from '../stores/products';
import { useT } from '../i18n';

const auth = useAuthStore();
const products = useProductsStore();
const t = useT();

onMounted(() => {
    products.fetch().catch(() => {});
    auth.fetchMe().catch(() => {});
});
</script>

<template>
    <h1 class="h4 mb-4">{{ t('dash.title') }}</h1>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="card kpi-card"><div class="card-body">
                <div class="text-muted small">{{ t('dash.store_status') }}</div>
                <div class="fs-4 fw-semibold text-capitalize">{{ auth.tenant?.status }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card kpi-card"><div class="card-body">
                <div class="text-muted small">{{ t('dash.products') }}</div>
                <div class="fs-4 fw-semibold">{{ products.total }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card kpi-card"><div class="card-body">
                <div class="text-muted small">{{ t('dash.credits') }}</div>
                <div class="fs-4 fw-semibold">{{ auth.credits }}</div>
            </div></div>
        </div>
    </div>
    <div class="alert alert-light border">{{ t('dash.help') }}</div>
</template>
