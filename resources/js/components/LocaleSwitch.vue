<script setup>
import { useUiStore } from '../stores/ui';

const ui = useUiStore();
</script>

<template>
    <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn" :class="ui.locale === 'en' ? 'btn-primary' : 'btn-outline-secondary'" @click="ui.setLocale('en')">EN</button>
        <button type="button" class="btn" :class="ui.locale === 'ar' ? 'btn-primary' : 'btn-outline-secondary'" @click="ui.setLocale('ar')">عربي</button>
    </div>
</template>
