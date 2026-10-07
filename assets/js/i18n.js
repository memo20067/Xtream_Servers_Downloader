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
        error_loading: "Error loading content. Please check server credentials.",
        catalog_title: "Search your library",
        catalog_subtitle: "Find a channel or title in the selected source.",
        search_scope: "Search scope:",
        current_source: "Current source",
        source_scope_note: "Results change when you switch source.",
        search_in_source: "Search in selected source",
        source_search_note: "Results are limited to the selected source and content type.",
        catalog_results: "Results",
        artwork: "Artwork",
        content_item: "Content",
        action: "Action",
        source_label: "Source",
        content_type: "Content type",
        tab_live: "Live TV",
        tab_movies: "Movies",
        tab_series: "Series",
        results_label: "results",
        no_source: "No source has been added yet.",
        no_search_results: "No results match this search in the selected source.",
        no_category_results: "No results are available in this category.",
        retry: "Try again",
        clear_search: "Clear search",
        untitled: "Untitled",
        live_channel: "Live channel",
        movie_title: "Movie",
        series_title: "Series",
        play_channel: "Play channel",
        play_movie: "Play movie",
        view_episodes: "View episodes",
        download_plan: "See download plans",
        download_file: "Download file",
        download_source_quality: "Download source quality",
        source_quality_note: "The file downloads in the quality provided by your source. This app does not transcode it into another resolution.",
        cancel: "Cancel",
        download_failed: "The download could not be started.",
        download_target_missing: "The source item could not be identified.",
        preparing_download: "Preparing download…",
        download_started: "The source file download has been requested.",
        direct_play_unavailable: "Direct playback is unavailable in this browser session.",
        playlist_add_failed: "Could not add the playlist.",
        pending_receipts: "Receipts awaiting review",
        no_pending_receipts: "No receipts are waiting for review",
        payments_records: "Payment records",
        continue_managing: "Continue managing",
        broadcast_sources: "Broadcast sources",
        accounts_subscriptions: "Accounts and subscriptions",
        pricing_benefits: "Pricing and benefits"
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
        error_loading: "حدث خطأ أثناء تحميل المحتوى. يرجى التحقق من بيانات الخادم.",
        catalog_title: "ابحث في مكتبتك",
        catalog_subtitle: "اعثر على قناة أو عنوان داخل المصدر الذي اخترته.",
        search_scope: "نطاق البحث:",
        current_source: "المصدر الحالي",
        source_scope_note: "تتغير النتائج عند تبديل المصدر.",
        search_in_source: "ابحث في المصدر المحدد",
        source_search_note: "تقتصر النتائج على المصدر ونوع المحتوى المحددين.",
        catalog_results: "النتائج",
        artwork: "الصورة",
        content_item: "المحتوى",
        action: "الإجراء",
        source_label: "المصدر",
        content_type: "نوع المحتوى",
        tab_live: "البث المباشر",
        tab_movies: "الأفلام",
        tab_series: "المسلسلات",
        results_label: "نتيجة",
        no_source: "لم تتم إضافة أي مصدر بعد.",
        no_search_results: "لا توجد نتائج تطابق البحث في المصدر المحدد.",
        no_category_results: "لا توجد نتائج في هذا التصنيف.",
        retry: "إعادة المحاولة",
        clear_search: "مسح البحث",
        untitled: "بدون عنوان",
        live_channel: "قناة مباشرة",
        movie_title: "فيلم",
        series_title: "مسلسل",
        play_channel: "تشغيل القناة",
        play_movie: "تشغيل الفيلم",
        view_episodes: "عرض الحلقات",
        download_plan: "عرض باقات التنزيل",
        download_file: "تنزيل الملف",
        download_source_quality: "تنزيل جودة المصدر",
        source_quality_note: "يُنزّل الملف بالجودة التي يوفرها المصدر. لا يحوّل التطبيق الملف إلى دقة أخرى.",
        cancel: "إلغاء",
        download_failed: "تعذر بدء التنزيل.",
        download_target_missing: "تعذر تحديد العنصر المطلوب تنزيله.",
        preparing_download: "جارٍ تجهيز التنزيل…",
        download_started: "تم طلب تنزيل الملف من المصدر.",
        direct_play_unavailable: "التشغيل المباشر غير متاح في جلسة المتصفح هذه.",
        playlist_add_failed: "تعذرت إضافة قائمة التشغيل.",
        pending_receipts: "إيصالات بانتظار المراجعة",
        no_pending_receipts: "لا توجد إيصالات بانتظار المراجعة",
        payments_records: "سجل الدفعات",
        continue_managing: "متابعة الإدارة",
        broadcast_sources: "مصادر البث",
        accounts_subscriptions: "الحسابات والاشتراكات",
        pricing_benefits: "الأسعار والمزايا"
    }
};

let currentLang = (() => {
    const serverLang = document.documentElement.lang;
    if (serverLang === 'ar' || serverLang === 'en') return serverLang;
    try {
        const savedLang = localStorage.getItem('app_lang');
        return savedLang === 'ar' || savedLang === 'en' ? savedLang : 'en';
    } catch (error) {
        return 'en';
    }
})();

function setLanguage(lang, reloadForLayout = false) {
    if (!i18nData[lang]) return;
    currentLang = lang;
    try {
        localStorage.setItem('app_lang', lang);
    } catch (error) {
        // Keep the current page usable when browser storage is disabled.
    }
    document.cookie = `app_lang=${encodeURIComponent(lang)}; Max-Age=31536000; Path=/; SameSite=Lax`;

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

    // Dispatch language change event for dynamic JS components.
    window.dispatchEvent(new CustomEvent('languageChanged', { detail: { lang: lang } }));
    if (reloadForLayout) window.location.reload();
}

function t(key) {
    return (i18nData[currentLang] && i18nData[currentLang][key]) ? i18nData[currentLang][key] : key;
}

document.addEventListener('DOMContentLoaded', () => {
    setLanguage(currentLang);
});
