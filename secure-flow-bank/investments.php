<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
hardenSession();
requireCsrf();

$user    = getCurrentUser($conn);
$userId  = $user['id'];
$accounts = getUserAccounts($conn, $userId);
$unreadNotifications = getUnreadNotifications($conn, $userId);

$success = $error = '';

/* ── Flash messages from PRG redirects ── */
if (!empty($_SESSION['inv_success'])) {
    $success = $_SESSION['inv_success'];
    unset($_SESSION['inv_success']);
}
if (!empty($_SESSION['inv_error'])) {
    $error = $_SESSION['inv_error'];
    unset($_SESSION['inv_error']);
}

/* ── Handle BUY ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buy') {
    checkHoneypot();
    $productId = (int)($_POST['product_id'] ?? 0);
    $accountId = (int)($_POST['account_id'] ?? 0);
    $rawAmount = $_POST['amount'] ?? '';
    $amount    = validateAmount($rawAmount);

    if (!$amount) {
        $error = 'المبلغ غير صالح.';
    } else {
        /* Verify product */
        $ps = $conn->prepare("SELECT * FROM investment_products WHERE id=? AND is_active=1");
        $ps->bind_param("i", $productId);
        $ps->execute();
        $product = $ps->get_result()->fetch_assoc();

        if (!$product) {
            $error = 'المنتج غير موجود.';
        } elseif ($amount < $product['min_amount']) {
            $error = 'الحد الأدنى للاستثمار هو ' . number_format($product['min_amount'], 2) . ' ر.س';
        } else {
            /* Verify account belongs to user */
            $as = $conn->prepare("SELECT * FROM accounts WHERE id=? AND user_id=? AND status='active'");
            $as->bind_param("ii", $accountId, $userId);
            $as->execute();
            $account = $as->get_result()->fetch_assoc();

            if (!$account) {
                $error = 'الحساب غير صالح.';
            } elseif ($account['balance'] < $amount) {
                $error = 'الرصيد غير كافٍ.';
            } else {
                $conn->begin_transaction();
                try {
                    /* Deduct from account */
                    $upd = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE id=? AND user_id=?");
                    $upd->bind_param("dii", $amount, $accountId, $userId);
                    $upd->execute();

                    /* Create investment record */
                    $units = $amount / max($product['min_amount'], 1);
                    $ins   = $conn->prepare("INSERT INTO user_investments (user_id, product_id, account_id, amount_invested, units, purchase_price, current_value) VALUES (?,?,?,?,?,?,?)");
                    $ins->bind_param("iiidddd", $userId, $productId, $accountId, $amount, $units, $product['min_amount'], $amount);
                    $ins->execute();

                    /* Log transaction */
                    $desc = 'استثمار في: ' . $product['name_ar'];
                    $tx = $conn->prepare("INSERT INTO transactions (from_account_id, type, amount, description, status, reference) VALUES (?,?,?,?,?,?)");
                    $type = 'payment'; $status = 'completed';
                    $ref  = 'INV-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
                    $tx->bind_param("isdsss", $accountId, $type, $amount, $desc, $status, $ref);
                    $tx->execute();

                    logSecurityEvent($conn, 'investment_purchase', $desc . ' بمبلغ ' . $amount, $userId);
                    $conn->commit();
                    $_SESSION['inv_success'] = 'تم الاستثمار بنجاح في ' . $product['name_ar'] . '!';
                    header('Location: investments.php');
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'حدث خطأ أثناء المعالجة. حاول مرة أخرى.';
                }
            }
        }
    }
}

/* ── Handle SELL ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sell') {
    checkHoneypot();
    $invId = (int)($_POST['investment_id'] ?? 0);

    $is = $conn->prepare("SELECT ui.*, ip.name_ar FROM user_investments ui JOIN investment_products ip ON ui.product_id=ip.id WHERE ui.id=? AND ui.user_id=? AND ui.status='active'");
    $is->bind_param("ii", $invId, $userId);
    $is->execute();
    $inv = $is->get_result()->fetch_assoc();

    if (!$inv) {
        $error = 'الاستثمار غير موجود.';
    } else {
        $conn->begin_transaction();
        try {
            /* Simulate growth: random between -2% and +15% */
            $growthRate   = (rand(-200, 1500) / 10000);
            $returnAmount = round($inv['amount_invested'] * (1 + $growthRate), 2);

            /* Credit account */
            $upd = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id=? AND user_id=?");
            $upd->bind_param("dii", $returnAmount, $inv['account_id'], $userId);
            $upd->execute();

            /* Mark sold */
            $sell = $conn->prepare("UPDATE user_investments SET status='sold', current_value=?, sold_at=NOW() WHERE id=?");
            $sell->bind_param("di", $returnAmount, $invId);
            $sell->execute();

            $profit = $returnAmount - $inv['amount_invested'];
            $desc   = 'بيع استثمار: ' . $inv['name_ar'];
            $tx = $conn->prepare("INSERT INTO transactions (to_account_id, type, amount, description, status, reference) VALUES (?,?,?,?,?,?)");
            $type = 'deposit'; $status = 'completed';
            $ref  = 'SELL-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
            $tx->bind_param("isdsss", $inv['account_id'], $type, $returnAmount, $desc, $status, $ref);
            $tx->execute();

            logSecurityEvent($conn, 'investment_sell', $desc, $userId);
            $conn->commit();
            $profitLabel = $profit >= 0 ? '+' . number_format($profit, 2) : number_format($profit, 2);
            $_SESSION['inv_success'] = 'تم بيع الاستثمار! استردادك: ' . number_format($returnAmount, 2) . ' ر.س (ربح/خسارة: ' . $profitLabel . ' ر.س)';
            header('Location: investments.php');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'حدث خطأ أثناء البيع.';
        }
    }
}

/* ── Fetch data ─────────────────────────────────────── */
$products = $conn->query("SELECT * FROM investment_products WHERE is_active=1 ORDER BY type, min_amount")->fetch_all(MYSQLI_ASSOC);

$myInvStmt = $conn->prepare("
    SELECT ui.*, ip.name_ar, ip.type, ip.expected_return, ip.icon
    FROM user_investments ui
    JOIN investment_products ip ON ui.product_id = ip.id
    WHERE ui.user_id = ? AND ui.status = 'active'
    ORDER BY ui.purchased_at DESC
");
$myInvStmt->bind_param("i", $userId);
$myInvStmt->execute();
$myInvestments = $myInvStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalInvested = array_sum(array_column($myInvestments, 'amount_invested'));

/* Simulate live current value (+random growth) */
$totalCurrent  = 0;
foreach ($myInvestments as &$inv) {
    $days   = max(1, round((time() - strtotime($inv['purchased_at'])) / 86400));
    $growth = ($inv['expected_return'] / 100) * ($days / 365);
    $inv['current_value'] = round($inv['amount_invested'] * (1 + $growth), 2);
    $inv['gain']          = $inv['current_value'] - $inv['amount_invested'];
    $inv['gain_pct']      = $inv['amount_invested'] > 0 ? round(($inv['gain'] / $inv['amount_invested']) * 100, 2) : 0;
    $totalCurrent        += $inv['current_value'];
}
unset($inv);

$totalGain    = $totalCurrent - $totalInvested;
$totalGainPct = $totalInvested > 0 ? round(($totalGain / $totalInvested) * 100, 2) : 0;

/* Sold history */
$soldStmt = $conn->prepare("
    SELECT ui.*, ip.name_ar, ip.type, ip.icon
    FROM user_investments ui
    JOIN investment_products ip ON ui.product_id = ip.id
    WHERE ui.user_id = ? AND ui.status = 'sold'
    ORDER BY ui.sold_at DESC LIMIT 10
");
$soldStmt->bind_param("i", $userId);
$soldStmt->execute();
$soldInvestments = $soldStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle    = 'الاستثمارات';
$pageSubtitle = 'إدارة محفظتك الاستثمارية';
require_once 'includes/header.php';

$riskLabel = ['low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'مرتفع'];
$riskClass = ['low' => 'badge-success', 'medium' => 'badge-warning', 'high' => 'badge-danger'];
$typeLabel  = [
    'stocks'     => 'أسهم',
    'bonds'      => 'سندات',
    'funds'      => 'صناديق',
    'gold'       => 'ذهب',
    'crypto'     => 'كريبتو',
    'realestate' => 'عقارات',
];
?>

<style>
/* ── Investments page styles ── */
.inv-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}
.inv-stat {
    background: var(--bg-surface);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    padding: 22px 24px;
    box-shadow: var(--shadow-sm);
}
.inv-stat.primary-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border: none;
    color: #fff;
}
.inv-stat.primary-card .inv-stat-label,
.inv-stat.primary-card .inv-stat-icon { color: rgba(255,255,255,0.75); }
.inv-stat.primary-card .inv-stat-value { color: #fff; }
.inv-stat-label { font-size: 12px; color: var(--gray-500); margin-bottom: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.inv-stat-value { font-size: 24px; font-weight: 800; color: var(--gray-900); }
.inv-stat-sub   { font-size: 12px; margin-top: 4px; }
.inv-stat-sub.up   { color: var(--green); }
.inv-stat-sub.down { color: var(--red); }

/* Products grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}
.product-card {
    background: var(--bg-surface);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    padding: 22px;
    box-shadow: var(--shadow-sm);
    transition: all var(--transition);
    position: relative;
    overflow: hidden;
}
.product-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}
.product-card.stocks::before    { background: linear-gradient(90deg, #f43f8a, #f43f8a); }
.product-card.bonds::before     { background: linear-gradient(90deg, #10b981, #34d399); }
.product-card.gold::before      { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.product-card.realestate::before{ background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.product-card.funds::before     { background: linear-gradient(90deg, #ef4444, #f87171); }
.product-card.crypto::before    { background: linear-gradient(90deg, #06b6d4, #22d3ee); }
.product-card:hover { box-shadow: var(--shadow); transform: translateY(-3px); border-color: var(--primary); }

.product-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; }
.product-icon {
    width: 46px; height: 46px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.product-icon.stocks     { background: #ffeef5; color: #1d4ed8; }
.product-icon.bonds      { background: #d1fae5; color: #065f46; }
.product-icon.gold       { background: #fef3c7; color: #92400e; }
.product-icon.realestate { background: #ede9fe; color: #5b21b6; }
.product-icon.funds      { background: #fee2e2; color: #991b1b; }
.product-icon.crypto     { background: #cffafe; color: #164e63; }

.product-name   { font-size: 15px; font-weight: 700; color: var(--gray-900); margin-bottom: 3px; }
.product-type   { font-size: 11px; color: var(--gray-500); }
.product-return { font-size: 22px; font-weight: 800; color: var(--green); }
.product-return span { font-size: 12px; font-weight: 500; color: var(--gray-500); }
.product-desc { font-size: 13px; color: var(--gray-500); line-height: 1.7; margin: 10px 0 14px; }
.product-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.product-min  { font-size: 12px; color: var(--gray-500); }
.product-min strong { color: var(--gray-800); }

/* My investments table */
.my-inv-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.my-inv-table th { background: var(--gray-50); padding: 11px 14px; text-align: right; font-size: 11px; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--gray-200); }
.my-inv-table td { padding: 14px; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
.my-inv-table tbody tr:hover { background: var(--gray-50); }
.my-inv-table tbody tr:last-child td { border-bottom: none; }

.inv-name-cell { display: flex; align-items: center; gap: 10px; }
.inv-icon-sm { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; }

.gain-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; padding: 3px 8px; border-radius: 20px; }
.gain-badge.up   { background: var(--green-light); color: var(--green); }
.gain-badge.down { background: var(--red-light);   color: var(--red); }

/* Buy modal */
.buy-form { display: none; background: var(--bg-surface2); border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 18px; margin-top: 12px; }
.buy-form.open { display: block; }

/* Tabs */
.inv-tabs { display: flex; gap: 6px; margin-bottom: 20px; border-bottom: 2px solid var(--gray-200); padding-bottom: 0; }
.inv-tab {
    padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer;
    border: none; background: none; color: var(--gray-500);
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    transition: all var(--transition); border-radius: 6px 6px 0 0;
}
.inv-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: var(--primary-light); }
.inv-tab:hover  { color: var(--primary); }

.tab-content { display: none; }
.tab-content.active { display: block; }

/* Filter chips */
.filter-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
.chip {
    padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    border: 1.5px solid var(--gray-200); background: var(--bg-surface);
    color: var(--gray-600); cursor: pointer; transition: all var(--transition);
}
.chip.active, .chip:hover { background: var(--primary); border-color: var(--primary); color: #fff; }

/* Empty state */
.inv-empty { text-align: center; padding: 50px 20px; color: var(--gray-400); }
.inv-empty i { font-size: 52px; display: block; margin-bottom: 16px; opacity: 0.35; }
.inv-empty p { font-size: 15px; margin-bottom: 16px; }
</style>

<?php if ($success): ?>
    <div class="alert alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error" data-auto-dismiss><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ── Portfolio Summary ── -->
<div class="inv-summary-grid">
    <div class="inv-stat primary-card">
        <div class="inv-stat-label">إجمالي المحفظة</div>
        <div class="inv-stat-value"><?= number_format($totalCurrent, 2) ?> <small style="font-size:14px;font-weight:500">ر.س</small></div>
        <div class="inv-stat-sub" style="color:rgba(255,255,255,0.75)"><?= count($myInvestments) ?> استثمار نشط</div>
    </div>
    <div class="inv-stat">
        <div class="inv-stat-label">إجمالي المستثمر</div>
        <div class="inv-stat-value"><?= number_format($totalInvested, 2) ?></div>
        <div class="inv-stat-sub" style="color:var(--gray-400)">ر.س</div>
    </div>
    <div class="inv-stat">
        <div class="inv-stat-label">الربح / الخسارة</div>
        <div class="inv-stat-value" style="color:<?= $totalGain >= 0 ? 'var(--green)' : 'var(--red)' ?>">
            <?= ($totalGain >= 0 ? '+' : '') . number_format($totalGain, 2) ?>
        </div>
        <div class="inv-stat-sub <?= $totalGain >= 0 ? 'up' : 'down' ?>">
            <?= ($totalGainPct >= 0 ? '+' : '') . $totalGainPct ?>%
        </div>
    </div>
    <div class="inv-stat">
        <div class="inv-stat-label">استثمارات مباعة</div>
        <div class="inv-stat-value"><?= count($soldInvestments) ?></div>
        <div class="inv-stat-sub" style="color:var(--gray-400)">عملية مكتملة</div>
    </div>
</div>

<!-- ── Tabs ── -->
<div class="inv-tabs">
    <button class="inv-tab active" onclick="switchTab('market')"><i class="fas fa-store"></i> السوق</button>
    <button class="inv-tab" onclick="switchTab('portfolio')"><i class="fas fa-briefcase"></i> محفظتي <?= count($myInvestments) > 0 ? '<span class="badge badge-info" style="margin-right:4px">'.count($myInvestments).'</span>' : '' ?></button>
    <button class="inv-tab" onclick="switchTab('history')"><i class="fas fa-clock-rotate-left"></i> السجل</button>
</div>

<!-- ════════ TAB 1: MARKET ════════ -->
<div id="tab-market" class="tab-content active">
    <div class="filter-chips" id="filterChips">
        <span class="chip active" onclick="filterProducts('all', this)">الكل</span>
        <span class="chip" onclick="filterProducts('stocks', this)">أسهم</span>
        <span class="chip" onclick="filterProducts('bonds', this)">سندات</span>
        <span class="chip" onclick="filterProducts('gold', this)">ذهب</span>
        <span class="chip" onclick="filterProducts('realestate', this)">عقارات</span>
        <span class="chip" onclick="filterProducts('funds', this)">صناديق</span>
    </div>

    <div class="products-grid" id="productsGrid">
        <?php foreach ($products as $p): ?>
        <div class="product-card <?= $p['type'] ?>" data-type="<?= $p['type'] ?>">
            <div class="product-header">
                <div>
                    <div class="product-name"><?= htmlspecialchars($p['name_ar']) ?></div>
                    <div class="product-type"><?= $typeLabel[$p['type']] ?? $p['type'] ?></div>
                </div>
                <div class="product-icon <?= $p['type'] ?>">
                    <i class="fas <?= htmlspecialchars($p['icon']) ?>"></i>
                </div>
            </div>

            <div class="product-return">
                <?= $p['expected_return'] ?>% <span>عائد متوقع سنوياً</span>
            </div>

            <p class="product-desc"><?= htmlspecialchars($p['description_ar']) ?></p>

            <div class="product-meta">
                <div class="product-min">
                    الحد الأدنى: <strong><?= number_format($p['min_amount'], 0) ?> ر.س</strong>
                </div>
                <span class="badge <?= $riskClass[$p['risk_level']] ?>">
                    مخاطرة <?= $riskLabel[$p['risk_level']] ?>
                </span>
            </div>

            <?php if (!empty($accounts)): ?>
            <button class="btn btn-primary btn-block btn-sm" onclick="toggleBuyForm(<?= $p['id'] ?>)">
                <i class="fas fa-plus"></i> استثمر الآن
            </button>

            <div class="buy-form" id="buyForm<?= $p['id'] ?>">
                <form method="POST">
                    <?= csrfField() ?><?= honeypotField() ?>
                    <input type="hidden" name="action" value="buy">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <div class="form-group" style="margin-bottom:10px">
                        <label class="form-group" style="font-size:12px;font-weight:600;color:var(--gray-600);display:block;margin-bottom:5px">من الحساب</label>
                        <select name="account_id" class="form-control" style="font-size:13px;padding:8px 12px">
                            <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= maskAccountNumber($acc['account_number']) ?> — <?= number_format($acc['balance'], 2) ?> ر.س</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:10px">
                        <label style="font-size:12px;font-weight:600;color:var(--gray-600);display:block;margin-bottom:5px">المبلغ (ر.س)</label>
                        <input type="number" name="amount" class="form-control" style="font-size:13px;padding:8px 12px"
                               min="<?= $p['min_amount'] ?>" step="0.01"
                               placeholder="الحد الأدنى <?= number_format($p['min_amount'], 0) ?> ر.س">
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="submit" class="btn btn-success btn-sm" style="flex:1"><i class="fas fa-check"></i> تأكيد</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleBuyForm(<?= $p['id'] ?>)">إلغاء</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="alert alert-warning" style="font-size:12px;padding:8px 12px;margin:0">لا يوجد حساب نشط</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ════════ TAB 2: PORTFOLIO ════════ -->
<div id="tab-portfolio" class="tab-content">
    <?php if (empty($myInvestments)): ?>
    <div class="inv-empty">
        <i class="fas fa-briefcase"></i>
        <p>لا توجد استثمارات نشطة بعد</p>
        <button class="btn btn-primary" onclick="switchTab('market')"><i class="fas fa-store"></i> تصفح السوق</button>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="my-inv-table">
                <thead>
                    <tr>
                        <th>الاستثمار</th>
                        <th>المبلغ المستثمر</th>
                        <th>القيمة الحالية</th>
                        <th>الربح / الخسارة</th>
                        <th>تاريخ الشراء</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myInvestments as $inv): ?>
                    <tr>
                        <td>
                            <div class="inv-name-cell">
                                <div class="inv-icon-sm <?= $inv['type'] ?>" style="background:var(--primary-light);color:var(--primary)">
                                    <i class="fas <?= htmlspecialchars($inv['icon']) ?>"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600;color:var(--gray-800)"><?= htmlspecialchars($inv['name_ar']) ?></div>
                                    <div style="font-size:11px;color:var(--gray-400)"><?= $typeLabel[$inv['type']] ?? '' ?></div>
                                </div>
                            </div>
                        </td>
                        <td><strong><?= number_format($inv['amount_invested'], 2) ?></strong> ر.س</td>
                        <td><strong style="color:var(--primary)"><?= number_format($inv['current_value'], 2) ?></strong> ر.س</td>
                        <td>
                            <span class="gain-badge <?= $inv['gain'] >= 0 ? 'up' : 'down' ?>">
                                <i class="fas fa-arrow-<?= $inv['gain'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= ($inv['gain'] >= 0 ? '+' : '') . number_format($inv['gain'], 2) ?> ر.س
                                (<?= ($inv['gain_pct'] >= 0 ? '+' : '') . $inv['gain_pct'] ?>%)
                            </span>
                        </td>
                        <td style="color:var(--gray-500);font-size:13px"><?= date('Y/m/d', strtotime($inv['purchased_at'])) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('هل تريد بيع هذا الاستثمار؟')">
                                <?= csrfField() ?><?= honeypotField() ?>
                                <input type="hidden" name="action" value="sell">
                                <input type="hidden" name="investment_id" value="<?= $inv['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-hand-holding-dollar"></i> بيع</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ════════ TAB 3: HISTORY ════════ -->
<div id="tab-history" class="tab-content">
    <?php if (empty($soldInvestments)): ?>
    <div class="inv-empty">
        <i class="fas fa-clock-rotate-left"></i>
        <p>لا يوجد سجل مبيعات بعد</p>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="my-inv-table">
                <thead>
                    <tr>
                        <th>الاستثمار</th>
                        <th>المبلغ المستثمر</th>
                        <th>المسترد</th>
                        <th>الربح / الخسارة</th>
                        <th>تاريخ البيع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($soldInvestments as $s):
                        $gain = $s['current_value'] - $s['amount_invested'];
                    ?>
                    <tr>
                        <td>
                            <div class="inv-name-cell">
                                <div class="inv-icon-sm" style="background:var(--gray-100);color:var(--gray-500)">
                                    <i class="fas <?= htmlspecialchars($s['icon']) ?>"></i>
                                </div>
                                <span style="font-weight:600"><?= htmlspecialchars($s['name_ar']) ?></span>
                            </div>
                        </td>
                        <td><?= number_format($s['amount_invested'], 2) ?> ر.س</td>
                        <td><strong><?= number_format($s['current_value'], 2) ?></strong> ر.س</td>
                        <td>
                            <span class="gain-badge <?= $gain >= 0 ? 'up' : 'down' ?>">
                                <?= ($gain >= 0 ? '+' : '') . number_format($gain, 2) ?> ر.س
                            </span>
                        </td>
                        <td style="color:var(--gray-500);font-size:13px"><?= $s['sold_at'] ? date('Y/m/d', strtotime($s['sold_at'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function switchTab(name) {
    document.querySelectorAll('.inv-tab').forEach((t,i) => {
        const tabs = ['market','portfolio','history'];
        t.classList.toggle('active', tabs[i] === name);
    });
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
}

function toggleBuyForm(id) {
    var form = document.getElementById('buyForm' + id);
    document.querySelectorAll('.buy-form.open').forEach(f => { if (f !== form) f.classList.remove('open'); });
    form.classList.toggle('open');
    if (form.classList.contains('open')) form.querySelector('input[name="amount"]').focus();
}

function filterProducts(type, el) {
    document.querySelectorAll('#filterChips .chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('#productsGrid .product-card').forEach(card => {
        card.style.display = (type === 'all' || card.dataset.type === type) ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
