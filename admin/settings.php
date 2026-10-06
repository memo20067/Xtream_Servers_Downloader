<?php
// admin/settings.php - Admin Site & Payment Settings Management
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Brand & Social settings
    $appName  = trim($_POST['app_name']  ?? '');
    $siteTitle= trim($_POST['site_title']?? '');
    $whatsapp = trim($_POST['whatsapp']  ?? '');
    $telegram = trim($_POST['telegram']  ?? '');
    $facebook = trim($_POST['facebook']  ?? '');
    $instagram= trim($_POST['instagram'] ?? '');

    setSetting('app_name',   $appName);
    setSetting('site_title', $siteTitle);
    setSetting('whatsapp',   $whatsapp);
    setSetting('telegram',   $telegram);
    setSetting('facebook',   $facebook);
    setSetting('instagram',  $instagram);

    // Payment method settings
    setSetting('pay_wallet',    trim($_POST['pay_wallet']    ?? ''));
    setSetting('pay_bank',      trim($_POST['pay_bank']      ?? ''));
    setSetting('pay_paypal',    trim($_POST['pay_paypal']    ?? ''));
    setSetting('pay_instapay',  trim($_POST['pay_instapay']  ?? ''));
    setSetting('pay_crypto_btc',trim($_POST['pay_crypto_btc']?? ''));
    setSetting('pay_crypto_eth',trim($_POST['pay_crypto_eth']?? ''));
    setSetting('pay_crypto_usdt',trim($_POST['pay_crypto_usdt']??''));
    setSetting('pay_currency',  trim($_POST['pay_currency']  ?? 'USD'));
    setSetting('pay_instructions', trim($_POST['pay_instructions'] ?? ''));

    $msg = 'Settings updated successfully!';
}

$appName   = getSetting('app_name',   'Xtream IPTV Player');
$siteTitle = getSetting('site_title', 'Xtream Media Hub');
$whatsapp  = getSetting('whatsapp',   '');
$telegram  = getSetting('telegram',   '');
$facebook  = getSetting('facebook',   '');
$instagram = getSetting('instagram',  '');

$payWallet    = getSetting('pay_wallet',    '');
$payBank      = getSetting('pay_bank',      '');
$payPaypal    = getSetting('pay_paypal',    '');
$payInstapay  = getSetting('pay_instapay',  '');
$payCryptoBtc = getSetting('pay_crypto_btc','');
$payCryptoEth = getSetting('pay_crypto_eth','');
$payCryptoUsdt= getSetting('pay_crypto_usdt','');
$payCurrency  = getSetting('pay_currency',  'USD');
$payInstructions = getSetting('pay_instructions', '');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-gear me-2"></i>Global Application Settings</h3>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST">
<div class="row g-4">
    <!-- Brand Info -->
    <div class="col-lg-6">
        <div class="card bg-secondary text-white border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h5 class="text-info mb-3"><i class="bi bi-display me-2"></i>Brand Info</h5>
                <div class="mb-3">
                    <label class="form-label">Application Name</label>
                    <input type="text" name="app_name" class="form-control bg-dark text-white border-secondary"
                           value="<?= htmlspecialchars($appName) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Site Title</label>
                    <input type="text" name="site_title" class="form-control bg-dark text-white border-secondary"
                           value="<?= htmlspecialchars($siteTitle) ?>" required>
                </div>

                <h5 class="text-info mb-3 mt-4"><i class="bi bi-chat-left-dots me-2"></i>Social Contacts</h5>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label"><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp</label>
                        <input type="text" name="whatsapp" class="form-control bg-dark text-white border-secondary"
                               value="<?= htmlspecialchars($whatsapp) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><i class="bi bi-telegram me-1 text-info"></i>Telegram</label>
                        <input type="text" name="telegram" class="form-control bg-dark text-white border-secondary"
                               value="<?= htmlspecialchars($telegram) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><i class="bi bi-facebook me-1 text-primary"></i>Facebook</label>
                        <input type="text" name="facebook" class="form-control bg-dark text-white border-secondary"
                               value="<?= htmlspecialchars($facebook) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><i class="bi bi-instagram me-1 text-warning"></i>Instagram</label>
                        <input type="text" name="instagram" class="form-control bg-dark text-white border-secondary"
                               value="<?= htmlspecialchars($instagram) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="col-lg-6">
        <div class="card bg-secondary text-white border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h5 class="text-warning mb-3"><i class="bi bi-credit-card me-2"></i>Payment Methods</h5>
                <p class="text-white-50 small mb-3">Configure the payment details shown to users when they subscribe. Leave empty to hide that method.</p>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label"><i class="bi bi-wallet2 me-1 text-success"></i>Mobile Wallet / Vodafone Cash Number</label>
                        <input type="text" name="pay_wallet" class="form-control bg-dark text-white border-secondary"
                               placeholder="e.g. 01012345678"
                               value="<?= htmlspecialchars($payWallet) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i class="bi bi-bank me-1 text-info"></i>Bank Account / IBAN</label>
                        <input type="text" name="pay_bank" class="form-control bg-dark text-white border-secondary"
                               placeholder="e.g. EG123456789012345678901234"
                               value="<?= htmlspecialchars($payBank) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i class="bi bi-paypal me-1 text-primary"></i>PayPal Email</label>
                        <input type="email" name="pay_paypal" class="form-control bg-dark text-white border-secondary"
                               placeholder="e.g. payments@yourdomain.com"
                               value="<?= htmlspecialchars($payPaypal) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i class="bi bi-phone me-1 text-warning"></i>InstaPay / Fawry ID</label>
                        <input type="text" name="pay_instapay" class="form-control bg-dark text-white border-secondary"
                               placeholder="e.g. @yourinstapay or Fawry code"
                               value="<?= htmlspecialchars($payInstapay) ?>">
                    </div>
                    <div class="col-12"><hr class="border-secondary my-1"></div>
                    <div class="col-12">
                        <label class="form-label"><i class="bi bi-currency-bitcoin me-1 text-warning"></i>Crypto — Bitcoin (BTC) Address</label>
                        <input type="text" name="pay_crypto_btc" class="form-control bg-dark text-white border-secondary"
                               placeholder="BTC wallet address"
                               value="<?= htmlspecialchars($payCryptoBtc) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Ethereum (ETH)</label>
                        <input type="text" name="pay_crypto_eth" class="form-control bg-dark text-white border-secondary"
                               placeholder="ETH address"
                               value="<?= htmlspecialchars($payCryptoEth) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label">USDT (TRC20/ERC20)</label>
                        <input type="text" name="pay_crypto_usdt" class="form-control bg-dark text-white border-secondary"
                               placeholder="USDT address"
                               value="<?= htmlspecialchars($payCryptoUsdt) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><i class="bi bi-currency-exchange me-1"></i>Currency Label</label>
                        <input type="text" name="pay_currency" class="form-control bg-dark text-white border-secondary"
                               placeholder="USD / EGP / EUR…"
                               value="<?= htmlspecialchars($payCurrency) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Special Payment Instructions (shown to user)</label>
                        <textarea name="pay_instructions" class="form-control bg-dark text-white border-secondary" rows="3"
                                  placeholder="e.g. Send the exact amount, include your username in the note"><?= htmlspecialchars($payInstructions) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-save me-1"></i>Save All Settings
    </button>
</div>
</form>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
