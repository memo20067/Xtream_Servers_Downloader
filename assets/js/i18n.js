// assets/js/i18n.js - Multi-language translation dictionary & controller

const i18nData = {
    en: {
        app_title: "Xtream IPTV Player",
        select_server: "Select IPTV Server",
        add_server: "Add Server",
        live_tv: "Live TV",
        movies: "Movies",
        series: "Series",
        search_placeholder: "Search channels or titles...",
        all_categories: "All Categories",
        play: "Play",
        download: "Download",
        back_to_series: "Back to Series List",
        seasons: "Seasons",
        episodes: "Episodes",
        season: "Season",
        episode: "Episode",
        no_content: "No content available in this category.",
        sign_in: "Sign In",
        sign_up: "Sign Up",
        logout: "Logout",
        admin_panel: "Admin Panel",
        my_account: "My Account",
        free_user: "Free Account",
        paid_user: "Paid Subscription",
        add_personal_server: "Add Personal Xtream Server",
        server_name: "Server Name",
        host_url: "Host / URL",
        username: "Username",
        password: "Password",
        close: "Close",
        save: "Save Server",
        loading: "Loading content...",
        error_loading: "Error loading content. Please check server credentials."
    },
    ar: {
        app_title: "مشغل اكستريم IPTV",
        select_server: "اختر خادم IPTV",
        add_server: "إضافة خادم",
        live_tv: "البث المباشر",
        movies: "الأفلام",
        series: "المسلسلات",
        search_placeholder: "ابحث عن القنوات أو العناوين...",
        all_categories: "جميع التصنيفات",
        play: "تشغيل",
        download: "تحميل",
        back_to_series: "العودة لقائمة المسلسلات",
        seasons: "المواسم",
        episodes: "الحلقات",
        season: "الموسم",
        episode: "الحلقة",
        no_content: "لا يتوفر محتوى في هذا التصنيف.",
        sign_in: "تسجيل الدخول",
        sign_up: "إنشاء حساب",
        logout: "تسجيل الخروج",
        admin_panel: "لوحة التحكم",
        my_account: "حسابي",
        free_user: "حساب مجاني",
        paid_user: "اشتراك مدفوع",
        add_personal_server: "إضافة خادم اكستريم شخصي",
        server_name: "اسم الخادم",
        host_url: "المضيف / الرابط",
        username: "اسم المستخدم",
        password: "كلمة المرور",
        close: "إغلاق",
        save: "حفظ الخادم",
        loading: "جاري تحميل المحتوى...",
        error_loading: "حدث خطأ أثناء تحميل المحتوى. يرجى التحقق من بيانات الخادم."
    }
};

let currentLang = localStorage.getItem('app_lang') || 'en';

function setLanguage(lang) {
    if (!i18nData[lang]) return;
    currentLang = lang;
    localStorage.setItem('app_lang', lang);

    const htmlElem = document.documentElement;
    htmlElem.setAttribute('lang', lang);
    htmlElem.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');

    // Update text for all data-i18n elements
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (i18nData[lang][key]) {
            if (el.tagName === 'INPUT' && el.getAttribute('placeholder')) {
                el.placeholder = i18nData[lang][key];
            } else {
                el.innerText = i18nData[lang][key];
            }
        }
    });

    // Update active state on language switcher buttons
    document.querySelectorAll('.lang-btn').forEach(btn => {
        if (btn.getAttribute('data-lang') === lang) {
            btn.classList.add('active', 'btn-primary');
            btn.classList.remove('btn-outline-secondary');
        } else {
            btn.classList.remove('active', 'btn-primary');
            btn.classList.add('btn-outline-secondary');
        }
    });

    // Dispatch language change event for dynamic JS components
    window.dispatchEvent(new CustomEvent('languageChanged', { detail: { lang: lang } }));
}

function t(key) {
    return (i18nData[currentLang] && i18nData[currentLang][key]) ? i18nData[currentLang][key] : key;
}

document.addEventListener('DOMContentLoaded', () => {
    setLanguage(currentLang);
});
