<?php
if (!defined('BASE_URL')) { http_response_code(403); exit('Direct access not permitted.'); }
/**
 * config/paymongo.php
 * Credentials for the PayMongo integration (GCash, PayMaya, GoTyme).
 *
 * Where to get these: PayMongo Dashboard -> Developers -> API Keys.
 *
 *  - TEST keys (sk_test_..., pk_test_...) work as soon as you finish
 *    basic sign-up + KYC (email + government ID + liveness check).
 *    You do NOT need a TIN or full business verification (KYB) to
 *    build and test this whole flow — test mode never moves real
 *    money, so build against sk_test_/pk_test_ today.
 *
 *  - LIVE keys (sk_live_..., pk_live_...) only work once your account
 *    is "Activated" for real payments, which does require KYB
 *    (business info + TIN) for most business types. Swap the values
 *    below to your live keys + live webhook secret when that's done —
 *    nothing else in the code needs to change.
 *
 * Never commit real keys to a public repo. If this project is on
 * GitHub, add config/paymongo.php to .gitignore and commit
 * config/paymongo.example.php instead.
 */

define('PAYMONGO_API_BASE', 'https://api.paymongo.com/v2');

/**
 * Which payment options tenants see on the Payments page.
 * Choose from: 'gcash', 'paymaya', 'gotyme'.
 *
 * Each PayMongo account has its own list of activated payment methods
 * (PayMongo Dashboard -> Settings -> Payment methods). If one of these
 * isn't active on your account yet, remove it here and tenants won't
 * be offered a button that would fail at checkout.
 *
 * 'gotyme' is paid through QR Ph — PayMongo has no separate GoTyme
 * method, so it needs "QR Ph" active on your account. The tenant scans
 * the QR code with the GoTyme app.
 */
define('PAYMONGO_ENABLED_METHODS', ['gcash', 'paymaya', 'gotyme']);

// PayMongo Dashboard -> Developers -> Webhooks -> (your webhook) -> Signing secret.
// Only needed once webhooks/paymongo.php is registered at a public URL
// (see docs/GCASH_SETUP.md) — on plain localhost the webhook can't
// reach you at all, so this can stay a placeholder during local dev;
// tenant/payments.php falls back to checking the Checkout Session
// directly when the tenant lands back on the return URL.
define('PAYMONGO_WEBHOOK_SECRET', 'whsec_REPLACE_ME');

/** True once real test/live keys have been filled in above. */
function paymongo_configured(): bool
{
    return PAYMONGO_SECRET_KEY !== 'sk_test_REPLACE_ME' && PAYMONGO_SECRET_KEY !== '';
}
