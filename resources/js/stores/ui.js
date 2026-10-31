import { defineStore } from 'pinia';
import ltrCss from 'bootstrap/dist/css/bootstrap.min.css?url';
import rtlCss from 'bootstrap/dist/css/bootstrap.rtl.min.css?url';

let toastId = 0;

export const useUiStore = defineStore('ui', {
    state: () => ({
        locale: localStorage.getItem('seller_hub_locale') || 'en',
        toasts: [],
    }),
    getters: {
        dir: (state) => (state.locale === 'ar' ? 'rtl' : 'ltr'),
    },
    actions: {
        /** Swap Bootstrap's LTR/RTL build and the html dir/lang attributes. */
        applyLocale() {
            let link = document.getElementById('bs-css');
            if (!link) {
                link = document.createElement('link');
                link.id = 'bs-css';
                link.rel = 'stylesheet';
                document.head.prepend(link);
            }
            link.href = this.dir === 'rtl' ? rtlCss : ltrCss;
            document.documentElement.dir = this.dir;
            document.documentElement.lang = this.locale;
        },
        setLocale(locale) {
            this.locale = locale === 'ar' ? 'ar' : 'en';
            localStorage.setItem('seller_hub_locale', this.locale);
            this.applyLocale();
        },
        toast(message, variant = 'danger') {
            const id = ++toastId;
            this.toasts.push({ id, message, variant });
            setTimeout(() => this.dismiss(id), 5000);
        },
        dismiss(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    },
});
