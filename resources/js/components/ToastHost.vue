<script setup>
import { useUiStore } from '../stores/ui';

const ui = useUiStore();
</script>

<template>
    <div class="toast-host">
        <div v-for="t in ui.toasts" :key="t.id" class="toast show align-items-center text-bg-light border" :class="`border-${t.variant}`" role="alert">
            <div class="d-flex">
                <div class="toast-body" :class="`text-${t.variant}`">{{ t.message }}</div>
                <button type="button" class="btn-close mx-2 m-auto" @click="ui.dismiss(t.id)"></button>
            </div>
        </div>
    </div>
</template>
