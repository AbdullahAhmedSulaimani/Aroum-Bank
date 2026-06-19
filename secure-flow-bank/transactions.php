<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
$user  = getCurrentUser($conn);
$unreadNotifications = getUnreadNotifications($conn, $user['id']);

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;
$type   = sanitizeInput($_GET['type'] ?? '');

$where  = "WHERE (fa.user_id=? OR ta.user_id=?)";
$params = [$user['id'], $user['id']];
$types  = "ii";
if ($type && in_array($type,['transfer','deposit','withdrawal','payment'])) {
    $where  .= " AND t.type=?";
    $params[]= $type;
    $types  .= "s";
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM transactions t LEFT JOIN accounts fa ON t.from_account_id=fa.id LEFT JOIN accounts ta ON t.to_account_id=ta.id $where");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['cnt'];
$pages = ceil($total / $limit);

$params[] = $limit;
$params[] = $offset;
$types   .= "ii";
$stmt = $conn->prepare("SELECT t.*, fa.account_number AS from_num, ta.account_number AS to_num FROM transactions t LEFT JOIN accounts fa ON t.from_account_id=fa.id LEFT JOIN accounts ta ON t.to_account_id=ta.id $where ORDER BY t.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'سجل العمليات';
include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clock-rotate-left"></i> سجل المعاملات</h3>
        <div style="display:flex;gap:8px;">
            <?php foreach ([''=>'الكل','transfer'=>'تحويل','deposit'=>'إيداع','withdrawal'=>'سحب','payment'=>'دفع'] as $v=>$l): ?>
            <a href="?type=<?= $v ?>" class="btn btn-sm <?= $type===$v?'btn-primary':'' ?>"><?= $l ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($transactions)): ?>
    <div class="empty-state"><i class="fas fa-receipt"></i><p>لا توجد معاملات.</p></div>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>النوع</th><th>المبلغ</th><th>من</th><th>إلى</th><th>البيان</th><th>المرجع</th><th>التاريخ</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $tx): ?>
        <tr>
            <td><span class="tx-type-badge <?= $tx['type'] ?>"><?= htmlspecialchars($tx['type']) ?></span></td>
            <td class="<?= in_array($tx['type'],['deposit'])?'text-green':'text-red' ?>"><?= number_format($tx['amount'],2) ?> ر.س</td>
            <td><?= $tx['from_num'] ? maskAccountNumber($tx['from_num']) : '—' ?></td>
            <td><?= $tx['to_num']   ? maskAccountNumber($tx['to_num'])   : '—' ?></td>
            <td><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
            <td><small><?= htmlspecialchars($tx['reference'] ?? '—') ?></small></td>
            <td><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></td>
            <td><span class="status-badge <?= $tx['status'] ?>"><?= $tx['status']==='completed'?'مكتمل':'معلق' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php for ($p=1;$p<=$pages;$p++): ?>
        <a href="?page=<?= $p ?>&type=<?= $type ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
