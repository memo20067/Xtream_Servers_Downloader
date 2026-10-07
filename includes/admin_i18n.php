<?php
// includes/admin_i18n.php - Admin Localization Dictionary

function get_admin_lang() {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
        $_SESSION['admin_lang'] = $_GET['lang'];
    }
    return $_SESSION['admin_lang'] ?? 'ar';
}

function admin_t($key) {
    static $dict = null;
    $lang = get_admin_lang();

    if ($dict === null) {
        $dict = [
            'ar' => [
                // Navigation & Layout
                'admin_title' => 'لوحة تحكم المدير',
                'nav_dashboard' => 'الرئيسية',
                'nav_servers' => 'السيرفرات العامة',
                'nav_users' => 'إدارة المستخدمين',
                'nav_plans' => 'باقات الاشتراك',
                'nav_news' => 'شريط الأخبار',
                'nav_payments' => 'الدفعات',
                'nav_pages' => 'الصفحات المخصصة',
                'nav_widgets' => 'الودجات',
                'nav_settings' => 'إعدادات الموقع',
                'nav_logs' => 'سجل الأخطاء',
                'nav_player' => 'لوحة المشغل الرئيسي',
                'nav_logout' => 'تسجيل الخروج',

                // Dashboard Overview
                'dash_welcome' => 'مرحباً بك في لوحة تحكم المدير',
                'dash_sub' => 'إدارة سيرفرات IPTV، باقات المشتركين، الإعلانات، والمستخدمين.',
                'dash_pending_title' => 'مراجعة الإيصالات المعلّقة',
                'dash_pending_sub' => 'تظهر هنا الدفعات التي تحتاج إلى مراجعة.',
                'dash_payments_link' => 'سجل الدفعات',
                'dash_pending_empty' => 'لا توجد إيصالات بانتظار المراجعة',
                'dash_pending_empty_sub' => 'ستظهر الإيصالات الجديدة في هذا القسم.',
                'dash_pending_count' => 'دفعات بانتظار المراجعة',
                'dash_continue' => 'متابعة الإدارة',
                'dash_server_action' => 'مصادر البث',
                'dash_user_action' => 'الحسابات والاشتراكات',
                'dash_plan_action' => 'الأسعار والمزايا',
                'stat_users' => 'إجمالي المستخدمين',
                'stat_paid' => 'المشتركين الفعّالين',
                'stat_servers' => 'السيرفرات العامة',
                'stat_plans' => 'باقات الاشتراك',
                'stat_news' => 'الإعلانات النشطة',

                // Servers Page
                'servers_title' => 'إدارة السيرفرات العامة',
                'btn_add_server' => 'إضافة سيرفر جديد',
                'tbl_id' => 'المعرّف',
                'tbl_name' => 'اسم السيرفر',
                'tbl_host' => 'الرابط / الهوست',
                'tbl_username' => 'اسم المستخدم',
                'tbl_m3u_url' => 'رابط بث M3U/M3U8 المباشر',
                'tbl_actions' => 'الإجراءات',
                'modal_add_server' => 'إضافة سيرفر IPTV جديد',
                'lbl_server_name' => 'اسم السيرفر',
                'lbl_host_url' => 'رابط السيرفر (Host)',
                'lbl_username' => 'اسم المستخدم',
                'lbl_password' => 'كلمة المرور',
                'lbl_m3u_url' => 'رابط قائمة قنوات M3U / M3U8 (اختياري)',
                'btn_save' => 'حفظ',
                'btn_cancel' => 'إلغاء',
                'msg_server_added' => 'تمت إضافة السيرفر بنجاح!',
                'msg_server_deleted' => 'تم حذف السيرفر بنجاح!',

                // Users Page
                'users_title' => 'إدارة المستخدمين',
                'tbl_user' => 'المستخدم',
                'tbl_contact' => 'بيانات التواصل',
                'tbl_role' => 'الصلحية',
                'tbl_status' => 'حالة الاشتراك',
                'tbl_plan' => 'الباقة الحالية',
                'btn_edit_user' => 'تعديل البيانات',
                'btn_toggle_status' => 'تبديل الاشتراك',
                'modal_edit_user' => 'تعديل حساب المستخدم',
                'lbl_email' => 'البريد الإلكتروني',
                'lbl_phone' => 'رقم الجوال',
                'lbl_role' => 'الرتبة / الصلاحية',
                'lbl_paid_status' => 'حالة الاشتراك المدفوع',
                'lbl_sub_plan' => 'باقة الاشتراك',
                'lbl_new_password' => 'كلمة مرور جديدة (اتركه فارغاً للإبقاء على الحالية)',
                'msg_user_updated' => 'تم تحديث حساب المستخدم بنجاح!',

                // Plans Page
                'plans_title' => 'إدارة باقات الاشتراك',
                'btn_add_plan' => 'إضافة باقة جديدة',
                'tbl_price' => 'السعر',
                'tbl_features' => 'المميزات والتفاصيل',
                'tbl_visibility' => 'الظهور في الموقع',
                'btn_show' => 'إظهار',
                'btn_hide' => 'إخفاء',
                'modal_add_plan' => 'إضافة باقة اشتراك جديدة',
                'modal_edit_plan' => 'تعديل باقة الاشتراك',
                'lbl_plan_name' => 'اسم الباقة',
                'lbl_price' => 'السعر',
                'lbl_currency' => 'العملة',
                'lbl_features' => 'المميزات (ميزة واحدة في كل سطر)',
                'lbl_is_visible' => 'إظهار الباقة للمستخدمين',
                'msg_plan_added' => 'تمت إضافة الباقة بنجاح!',
                'msg_plan_updated' => 'تم تحديث الباقة بنجاح!',
                'msg_plan_deleted' => 'تم حذف الباقة بنجاح!',

                // News Page
                'news_title' => 'إدارة شريط الأخبار والإعلانات',
                'btn_add_news' => 'نشر إعلان جديد',
                'tbl_severity' => 'مستوى الأهمية',
                'tbl_target' => 'الجمهور المستهدف',
                'tbl_message' => 'نص الإعلان',
                'tbl_expires' => 'تاريخ الانتهاء',
                'modal_add_news' => 'نشر إعلان في شريط الأخبار',
                'lbl_message' => 'نص الإعلان',
                'lbl_severity' => 'درجة الأهمية',
                'lbl_target_user' => 'المستخدم المستهدف (عام للجميع إذا ترك فارغاً)',
                'lbl_expires_at' => 'تاريخ ووقت الانتهاء (اختياري)',
                'sev_info' => 'معلومة (أزرق)',
                'sev_warning' => 'تنبيه (أصفر)',
                'sev_alert' => 'هام جداً / تحذير (أحمر)',
                'msg_news_posted' => 'تم نشر الإعلان بنجاح!',
                'msg_news_deleted' => 'تم حذف الإعلان بنجاح!',

                // Logs Page
                'logs_title' => 'سجل أخطاء النظام والاتصال',
                'btn_clear_logs' => 'مسح جميع السجلات',
                'tbl_timestamp' => 'الوقت والتاريخ',
                'tbl_level' => 'المستوى',
                'tbl_action' => 'الإجراء',
                'tbl_details' => 'تفاصيل الخطأ',
                'msg_logs_cleared' => 'تم مسح سجل الأخطاء بنجاح!',

                // Settings Page
                'settings_title' => 'إعدادات الموقع والهوية',
                'lbl_app_name' => 'اسم التطبيق',
                'lbl_site_title' => 'عنوان الموقع (Title)',
                'lbl_whatsapp' => 'رابط / رقم واتساب',
                'lbl_telegram' => 'رابط / معرف تليجرام',
                'lbl_facebook' => 'رابط صفحة فيسبوك',
                'lbl_instagram' => 'رابط حساب إنستغرام',
                'btn_save_settings' => 'حفظ الإعدادات',
                'msg_settings_saved' => 'تم حفظ الإعدادات بنجاح!'
            ],
            'en' => [
                // Navigation & Layout
                'admin_title' => 'Admin Dashboard',
                'nav_dashboard' => 'Dashboard',
                'nav_servers' => 'Global Servers',
                'nav_users' => 'User Management',
                'nav_plans' => 'Subscription Plans',
                'nav_news' => 'News Ticker',
                'nav_payments' => 'Payments',
                'nav_pages' => 'Custom Pages',
                'nav_widgets' => 'Widgets',
                'nav_settings' => 'Site Settings',
                'nav_logs' => 'Error Logs',
                'nav_player' => 'Main Player Dashboard',
                'nav_logout' => 'Logout',

                // Dashboard Overview
                'dash_welcome' => 'Welcome to Admin Dashboard',
                'dash_sub' => 'Manage IPTV servers, subscriber plans, announcements, and user profiles.',
                'dash_pending_title' => 'Review pending receipts',
                'dash_pending_sub' => 'Receipts awaiting review are listed here.',
                'dash_payments_link' => 'Payment records',
                'dash_pending_empty' => 'No receipts are waiting for review',
                'dash_pending_empty_sub' => 'Newly submitted receipts will appear here.',
                'dash_pending_count' => 'Receipts awaiting review',
                'dash_continue' => 'Continue managing',
                'dash_server_action' => 'Broadcast sources',
                'dash_user_action' => 'Accounts and subscriptions',
                'dash_plan_action' => 'Pricing and benefits',
                'stat_users' => 'Total Users',
                'stat_paid' => 'Active Paid Users',
                'stat_servers' => 'Global Servers',
                'stat_plans' => 'Subscription Plans',
                'stat_news' => 'Active News Items',

                // Servers Page
                'servers_title' => 'Global Server Management',
                'btn_add_server' => 'Add New Server',
                'tbl_id' => 'ID',
                'tbl_name' => 'Server Name',
                'tbl_host' => 'Host / URL',
                'tbl_username' => 'Username',
                'tbl_m3u_url' => 'Direct M3U/M3U8 Stream Link',
                'tbl_actions' => 'Actions',
                'modal_add_server' => 'Add IPTV Server',
                'lbl_server_name' => 'Server Name',
                'lbl_host_url' => 'Host / Server URL',
                'lbl_username' => 'Username',
                'lbl_password' => 'Password',
                'lbl_m3u_url' => 'M3U / M3U8 Stream URL (Optional)',
                'btn_save' => 'Save',
                'btn_cancel' => 'Cancel',
                'msg_server_added' => 'Server added successfully!',
                'msg_server_deleted' => 'Server deleted successfully!',

                // Users Page
                'users_title' => 'User Account Management',
                'tbl_user' => 'User',
                'tbl_contact' => 'Contact Details',
                'tbl_role' => 'Role',
                'tbl_status' => 'Subscription Status',
                'tbl_plan' => 'Active Plan',
                'btn_edit_user' => 'Edit Account',
                'btn_toggle_status' => 'Toggle Status',
                'modal_edit_user' => 'Edit User Profile',
                'lbl_email' => 'Email Address',
                'lbl_phone' => 'Mobile Phone Number',
                'lbl_role' => 'Role / Access Rank',
                'lbl_paid_status' => 'Paid Subscription Access',
                'lbl_sub_plan' => 'Assigned Plan',
                'lbl_new_password' => 'New Password (Leave blank to keep current)',
                'msg_user_updated' => 'User updated successfully!',

                // Plans Page
                'plans_title' => 'Subscription Plan Management',
                'btn_add_plan' => 'Add New Plan',
                'tbl_price' => 'Price',
                'tbl_features' => 'Features & Details',
                'tbl_visibility' => 'Frontend Visibility',
                'btn_show' => 'Show',
                'btn_hide' => 'Hide',
                'modal_add_plan' => 'Add Subscription Plan',
                'modal_edit_plan' => 'Edit Subscription Plan',
                'lbl_plan_name' => 'Plan Name',
                'lbl_price' => 'Price',
                'lbl_currency' => 'Currency',
                'lbl_features' => 'Features (One item per line)',
                'lbl_is_visible' => 'Visible on Frontend',
                'msg_plan_added' => 'Subscription plan created!',
                'msg_plan_updated' => 'Plan updated successfully!',
                'msg_plan_deleted' => 'Plan deleted successfully!',

                // News Page
                'news_title' => 'News Ticker Management',
                'btn_add_news' => 'Post Announcement',
                'tbl_severity' => 'Severity',
                'tbl_target' => 'Target Audience',
                'tbl_message' => 'Announcement Text',
                'tbl_expires' => 'Expiration Date',
                'modal_add_news' => 'Post Announcement',
                'lbl_message' => 'Message Text',
                'lbl_severity' => 'Severity Level',
                'lbl_target_user' => 'Target User (Global if blank)',
                'lbl_expires_at' => 'Expiration Date/Time (Optional)',
                'sev_info' => 'Info (Blue)',
                'sev_warning' => 'Warning (Yellow)',
                'sev_alert' => 'Alert (Red)',
                'msg_news_posted' => 'Announcement posted successfully!',
                'msg_news_deleted' => 'Announcement deleted successfully!',

                // Logs Page
                'logs_title' => 'System & Connection Error Logs',
                'btn_clear_logs' => 'Clear All Logs',
                'tbl_timestamp' => 'Timestamp',
                'tbl_level' => 'Level',
                'tbl_action' => 'Action',
                'tbl_details' => 'Error Details',
                'msg_logs_cleared' => 'Error logs cleared successfully!',

                // Settings Page
                'settings_title' => 'Site Configuration & Branding',
                'lbl_app_name' => 'Application Name',
                'lbl_site_title' => 'Site Title',
                'lbl_whatsapp' => 'WhatsApp Number / Link',
                'lbl_telegram' => 'Telegram Handle / Link',
                'lbl_facebook' => 'Facebook Page URL',
                'lbl_instagram' => 'Instagram Profile URL',
                'btn_save_settings' => 'Save Settings',
                'msg_settings_saved' => 'Site settings saved successfully!'
            ]
        ];
    }

    return $dict[$lang][$key] ?? $key;
}
