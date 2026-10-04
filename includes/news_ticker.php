<?php
// includes/news_ticker.php
require_once __DIR__ . '/auth.php';

function render_news_ticker() {
    $pdo = getDBConnection();
    $currentUser = getCurrentUser();
    $userId = $currentUser ? (int)$currentUser['id'] : 0;

    $tickerItems = [];
    $now = date('Y-m-d H:i:s');

    // Check if table exists
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM news_ticker
            WHERE (user_id IS NULL OR user_id = ?)
              AND (expires_at IS NULL OR expires_at > ?)
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmt->execute([$userId, $now]);
        $tickerItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tickerItems = [];
    }

    // Detect current language (check frontend session 'lang' or admin session 'admin_lang', default to 'ar')
    $lang = $_SESSION['lang'] ?? $_SESSION['admin_lang'] ?? 'ar';
    $isAr = ($lang === 'ar');

    // Add subscription expiration warning if user has active plan
    if ($currentUser && (!empty($currentUser['subscription_plan_id']) || !empty($currentUser['has_paid_subscription']))) {
        $welcomeMsg = $isAr
            ? 'مرحباً بعودتك، ' . htmlspecialchars($currentUser['username']) . '! اشتراكك مفعل حالياً. استمتع ببث IPTV عالي السرعة.'
            : 'Welcome back, ' . htmlspecialchars($currentUser['username']) . '! Your subscription is active. Enjoy high-speed IPTV streaming.';

        $tickerItems[] = [
            'severity' => 'info',
            'message' => $welcomeMsg
        ];
    } elseif ($currentUser && empty($currentUser['has_paid_subscription'])) {
        $warningMsg = $isAr
            ? 'تنبيه: تحويل وتنزيل الأفلام والمسلسلات مقيد حتى تقوم بالترقية إلى إحدى باقات الاشتراك المدفوعة.'
            : 'Notice: Downloads for Movies & Series are restricted until you upgrade to a paid subscription plan.';

        $tickerItems[] = [
            'severity' => 'warning',
            'message' => $warningMsg
        ];
    }

    if (empty($tickerItems)) {
        $defaultMsg = $isAr
            ? 'مرحباً بك في مشغل Nova IPTV - محتوى البث المباشر والأفلام والمسلسلات عالية الجودة'
            : 'Welcome to Nova IPTV Player - Premium Live Stream & On-Demand Content';

        $tickerItems[] = [
            'severity' => 'info',
            'message' => $defaultMsg
        ];
    }
    ?>
    <div class="news-ticker-bar">
        <div class="news-ticker-label">
            <i class="bi bi-broadcast me-2"></i>News
        </div>
        <div class="news-ticker-content">
            <div class="news-ticker-marquee">
                <?php foreach ($tickerItems as $item): ?>
                    <span class="news-ticker-item severity-<?= htmlspecialchars($item['severity']) ?>">
                        <?php if ($item['severity'] === 'alert'): ?>
                            <i class="bi bi-exclamation-octagon-fill me-1"></i>
                        <?php elseif ($item['severity'] === 'warning'): ?>
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <?php else: ?>
                            <i class="bi bi-info-circle-fill me-1"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($item['message']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}
