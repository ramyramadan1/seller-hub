import { useUiStore } from './stores/ui';

const messages = {
    en: {
        'app.name': 'Seller Hub',
        'nav.dashboard': 'Dashboard', 'nav.products': 'Products', 'nav.channels': 'Channels', 'nav.ai': 'AI Studio',
        'nav.orders': 'Orders', 'nav.analytics': 'Analytics', 'nav.settings': 'Settings', 'nav.soon': 'Coming soon',
        'auth.login': 'Sign in', 'auth.register': 'Create your store', 'auth.email': 'Email', 'auth.password': 'Password',
        'auth.password_confirm': 'Confirm password', 'auth.name': 'Your name', 'auth.store_name': 'Store name',
        'auth.currency': 'Currency', 'auth.language': 'Language', 'auth.no_account': 'New here? Create a store',
        'auth.have_account': 'Already have a store? Sign in', 'auth.logout': 'Log out',
        'setup.title': 'Setting up your store…', 'setup.body': 'We are building your private database. This usually takes a few seconds.',
        'setup.failed_title': 'We could not set up your store', 'setup.failed_body': 'Nothing was lost. You can try again.', 'setup.retry': 'Try again',
        'dash.title': 'Dashboard', 'dash.store_status': 'Store status', 'dash.products': 'Products', 'dash.credits': 'AI credits',
        'dash.help': 'Orders, revenue and profit charts will appear here once you connect a sales channel.',
        'products.title': 'Products', 'products.add': 'Add product', 'products.sku': 'SKU', 'products.name': 'Title',
        'products.price': 'Price', 'products.cost': 'Cost price', 'products.stock': 'Stock', 'products.save': 'Save',
        'products.empty': 'No products yet. Add your first one.', 'products.missing_cost': 'Missing cost', 'products.search': 'Search…',
        'common.loading': 'Loading…', 'common.cancel': 'Cancel',
    },
    ar: {
        'app.name': 'سيلر هب',
        'nav.dashboard': 'لوحة التحكم', 'nav.products': 'المنتجات', 'nav.channels': 'القنوات', 'nav.ai': 'استوديو الذكاء الاصطناعي',
        'nav.orders': 'الطلبات', 'nav.analytics': 'التحليلات', 'nav.settings': 'الإعدادات', 'nav.soon': 'قريباً',
        'auth.login': 'تسجيل الدخول', 'auth.register': 'أنشئ متجرك', 'auth.email': 'البريد الإلكتروني', 'auth.password': 'كلمة المرور',
        'auth.password_confirm': 'تأكيد كلمة المرور', 'auth.name': 'اسمك', 'auth.store_name': 'اسم المتجر',
        'auth.currency': 'العملة', 'auth.language': 'اللغة', 'auth.no_account': 'جديد هنا؟ أنشئ متجراً',
        'auth.have_account': 'لديك متجر؟ سجّل الدخول', 'auth.logout': 'تسجيل الخروج',
        'setup.title': 'جارٍ إعداد متجرك…', 'setup.body': 'نقوم بإنشاء قاعدة بياناتك الخاصة. يستغرق ذلك عادةً بضع ثوانٍ.',
        'setup.failed_title': 'تعذّر إعداد متجرك', 'setup.failed_body': 'لم يضع أي شيء. يمكنك المحاولة مرة أخرى.', 'setup.retry': 'حاول مرة أخرى',
        'dash.title': 'لوحة التحكم', 'dash.store_status': 'حالة المتجر', 'dash.products': 'المنتجات', 'dash.credits': 'رصيد الذكاء الاصطناعي',
        'dash.help': 'ستظهر هنا مخططات الطلبات والإيرادات والأرباح بعد ربط قناة بيع.',
        'products.title': 'المنتجات', 'products.add': 'إضافة منتج', 'products.sku': 'رمز المنتج', 'products.name': 'العنوان',
        'products.price': 'السعر', 'products.cost': 'سعر التكلفة', 'products.stock': 'المخزون', 'products.save': 'حفظ',
        'products.empty': 'لا توجد منتجات بعد. أضف أول منتج.', 'products.missing_cost': 'التكلفة ناقصة', 'products.search': 'بحث…',
        'common.loading': 'جارٍ التحميل…', 'common.cancel': 'إلغاء',
    },
};

/** Returns a translate function; reading ui.locale inside render keeps templates reactive. */
export function useT() {
    const ui = useUiStore();

    return (key) => messages[ui.locale]?.[key] ?? messages.en[key] ?? key;
}
