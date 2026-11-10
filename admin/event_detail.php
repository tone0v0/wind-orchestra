<?php
// admin/event_detail.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db_connection();
$event_id = (int)($_GET['id'] ?? 0);

if ($event_id <= 0) {
    header('Location: events.php');
    exit;
}

// 練習情報取得
$stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id');
$stmt->execute(['id' => $event_id]);
$event = $stmt->fetch();

if (!$event) {
    die('練習予定が見つかりませんでした。<a href="events.php">一覧へ戻る</a>');
}

// 学年表示マスタ
$grade_map = [
    '1' => '1年',
    '2' => '2年',
    '3' => '3年',
    '4' => '4年',
    'teacher' => '教師',
    'other' => 'その他'
];

// 全部員と、その練習の出欠状況をパート順・学年順に取得
$sql = "
    SELECT 
        u.id AS user_id,
        u.login_id,
        u.name AS user_name,
        u.grade,
        u.campus,
        u.role,
        i.id AS instrument_id,
        i.name AS instrument_name,
        i.section AS instrument_section,
        i.display_order,
        a.id AS attendance_id,
        a.status,
        DATE_FORMAT(a.target_time, '%H:%i') AS target_time,
        a.reason,
        a.updated_at
    FROM users u
    LEFT JOIN instruments i ON u.instrument_id = i.id
    LEFT JOIN attendances a ON u.id = a.user_id AND a.event_id = :event_id
    WHERE u.is_active = 1 AND u.role = 'student'
    ORDER BY u.campus ASC, COALESCE(i.display_order, 999) ASC, u.grade ASC, u.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['event_id' => $event_id]);
$members = $stmt->fetchAll();

// パートごとの集計 & ステータス別の部員リスト
$instruments_summary = [];
$total_counts = [
    'present' => 0,
    'late' => 0,
    'early' => 0,
    'absent' => 0,
    'unanswered' => 0,
];

$status_members = [
    'absent' => [],
    'late' => [],
    'early' => [],
    'unanswered' => [],
    'present' => [],
];

foreach ($members as $m) {
    $inst_name = $m['instrument_name'] ?? '未設定';
    if (!isset($instruments_summary[$inst_name])) {
        $instruments_summary[$inst_name] = [
            'section' => $m['instrument_section'] ?? 'Other',
            'present' => 0,
            'late' => 0,
            'early' => 0,
            'absent' => 0,
            'unanswered' => 0,
            'total' => 0,
            'members' => []
        ];
    }

    $st = $m['status'] ?: 'unanswered';
    if (isset($total_counts[$st])) {
        $total_counts[$st]++;
        $instruments_summary[$inst_name][$st]++;
    }
    $instruments_summary[$inst_name]['total']++;
    $instruments_summary[$inst_name]['members'][] = $m;
    $status_members[$st][] = $m;
}

$total_members = count($members);
$attendance_rate = $total_members > 0 ? round(($total_counts['present'] / $total_members) * 100) : 0;

$page_title = $event['title'] . ' 出欠詳細・集計';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.35rem;">
        <a href="events.php" style="color: var(--text-muted); text-decoration: none;">練習日程</a> &gt; 出欠詳細
    </div>
    <h1><?= h($event['title']) ?>（<?= h($event['event_date']) ?>）</h1>
    <div class="page-header-actions">
        <button type="button" class="btn btn-secondary btn-sm" id="btn-copy-summary">出欠テキストをコピー</button>
        <a href="events.php?action=edit&id=<?= $event['id'] ?>" class="btn btn-secondary btn-sm">練習情報を編集</a>
    </div>
</div>

<!-- 練習基本情報カード -->
<div class="card" style="padding: 1.25rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.95rem;">
        <div><strong>日時:</strong> <?= $event['event_date'] ?> <?= substr($event['start_time'], 0, 5) ?> 〜 <?= substr($event['end_time'], 0, 5) ?></div>
        <div><strong>場所:</strong> <?= h($event['location']) ?></div>
        <div><strong>練習内容:</strong> <?= h($event['description'] ?: '特になし') ?></div>
    </div>
</div>

<!-- 出欠一覧カード -->
<div class="card">
    <h2 class="card-title">出欠一覧</h2>

    <!-- 欠席者リスト -->
    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius); padding: 0.85rem 1rem; margin-bottom: 0.85rem;">
        <div style="font-weight: 700; color: #991b1b; font-size: 0.95rem; margin-bottom: 0.4rem;">
            欠席（<?= count($status_members['absent']) ?> 名）
        </div>
        <?php if (empty($status_members['absent'])): ?>
            <div style="font-size: 0.85rem; color: #991b1b;">なし</div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                <?php foreach ($status_members['absent'] as $m): ?>
                    <div style="font-size: 0.85rem; color: #7f1d1d; background: #ffffff; padding: 0.45rem 0.75rem; border-radius: 4px; border: 1px solid #fecaca;">
                        <strong><?= h($m['user_name']) ?></strong>
                        <span style="color: var(--text-muted); font-size: 0.8rem;">
                            （<?= h($m['campus'] ?? '津校') ?> / <?= h($m['instrument_name'] ?? '未設定') ?> / <?= h($grade_map[$m['grade']] ?? $m['grade']) ?>）
                        </span>
                        <?php if (!empty($m['reason'])): ?>
                            <span style="color: #991b1b; margin-left: 0.5rem; font-size: 0.8rem;">
                                理由: <?= h($m['reason']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 遅刻者リスト -->
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius); padding: 0.85rem 1rem; margin-bottom: 0.85rem;">
        <div style="font-weight: 700; color: #92400e; font-size: 0.95rem; margin-bottom: 0.4rem;">
            遅刻（<?= count($status_members['late']) ?> 名）
        </div>
        <?php if (empty($status_members['late'])): ?>
            <div style="font-size: 0.85rem; color: #92400e;">なし</div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                <?php foreach ($status_members['late'] as $m): ?>
                    <div style="font-size: 0.85rem; color: #78350f; background: #ffffff; padding: 0.45rem 0.75rem; border-radius: 4px; border: 1px solid #fde68a;">
                        <strong><?= h($m['user_name']) ?></strong>
                        <span style="color: var(--text-muted); font-size: 0.8rem;">
                            （<?= h($m['campus'] ?? '津校') ?> / <?= h($m['instrument_name'] ?? '未設定') ?> / <?= h($grade_map[$m['grade']] ?? $m['grade']) ?>）
                        </span>
                        <?php if (!empty($m['target_time'])): ?>
                            <span style="font-weight: 600; color: #b45309; margin-left: 0.4rem; font-size: 0.8rem;">
                                <?= h($m['target_time']) ?> 到着予定
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($m['reason'])): ?>
                            <span style="color: #78350f; margin-left: 0.4rem; font-size: 0.8rem;">
                                理由: <?= h($m['reason']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 早退者リスト -->
    <div style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: var(--radius); padding: 0.85rem 1rem; margin-bottom: 0.85rem;">
        <div style="font-weight: 700; color: #9a3412; font-size: 0.95rem; margin-bottom: 0.4rem;">
            早退（<?= count($status_members['early']) ?> 名）
        </div>
        <?php if (empty($status_members['early'])): ?>
            <div style="font-size: 0.85rem; color: #9a3412;">なし</div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                <?php foreach ($status_members['early'] as $m): ?>
                    <div style="font-size: 0.85rem; color: #78350f; background: #ffffff; padding: 0.45rem 0.75rem; border-radius: 4px; border: 1px solid #fed7aa;">
                        <strong><?= h($m['user_name']) ?></strong>
                        <span style="color: var(--text-muted); font-size: 0.8rem;">
                            （<?= h($m['campus'] ?? '津校') ?> / <?= h($m['instrument_name'] ?? '未設定') ?> / <?= h($grade_map[$m['grade']] ?? $m['grade']) ?>）
                        </span>
                        <?php if (!empty($m['target_time'])): ?>
                            <span style="font-weight: 600; color: #c2410c; margin-left: 0.4rem; font-size: 0.8rem;">
                                <?= h($m['target_time']) ?> 退出予定
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($m['reason'])): ?>
                            <span style="color: #78350f; margin-left: 0.4rem; font-size: 0.8rem;">
                                理由: <?= h($m['reason']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 出席者リスト -->
    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius); padding: 0.85rem 1rem; margin-bottom: 0.85rem;">
        <div style="font-weight: 700; color: #065f46; font-size: 0.95rem; margin-bottom: 0.4rem;">
            出席（<?= count($status_members['present']) ?> 名）
        </div>
        <?php if (empty($status_members['present'])): ?>
            <div style="font-size: 0.85rem; color: #065f46;">なし</div>
        <?php else: ?>
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                <?php foreach ($status_members['present'] as $m): ?>
                    <span style="font-size: 0.85rem; background: #ffffff; border: 1px solid #a7f3d0; padding: 0.25rem 0.6rem; border-radius: 4px; color: #047857; font-weight: 500;">
                        <?= h($m['user_name']) ?> <span style="font-size: 0.75rem; color: #059669;">(<?= h($m['instrument_name'] ?? '未設定') ?>)</span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 未回答者リスト -->
    <div style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: var(--radius); padding: 0.85rem 1rem;">
        <div style="font-weight: 700; color: #475569; font-size: 0.95rem; margin-bottom: 0.4rem;">
            未回答（<?= count($status_members['unanswered']) ?> 名）
        </div>
        <?php if (empty($status_members['unanswered'])): ?>
            <div style="font-size: 0.85rem; color: #475569;">なし（全員回答済み）</div>
        <?php else: ?>
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                <?php foreach ($status_members['unanswered'] as $m): ?>
                    <span style="font-size: 0.85rem; background: #ffffff; border: 1px solid #cbd5e1; padding: 0.25rem 0.6rem; border-radius: 4px; color: #334155;">
                        <?= h($m['user_name']) ?> <span style="font-size: 0.75rem; color: var(--text-muted);">(<?= h($m['instrument_name'] ?? '未設定') ?>)</span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// 出欠テキストコピー機能（LINEや連絡板用）
document.getElementById('btn-copy-summary').addEventListener('click', () => {
    let text = `【<?= addslashes($event['event_date']) ?> <?= addslashes($event['title']) ?> 出欠状況】\n`;
    text += `出席: <?= $total_counts['present'] ?>名 / 遅刻: <?= $total_counts['late'] ?>名 / 早退: <?= $total_counts['early'] ?>名 / 欠席: <?= $total_counts['absent'] ?>名 / 未回答: <?= $total_counts['unanswered'] ?>名\n\n`;
    
    text += `■ 欠席者:\n`;
    <?php if (empty($status_members['absent'])): ?>
        text += `・なし\n`;
    <?php else: ?>
        <?php foreach ($status_members['absent'] as $m): ?>
            text += `・[<?= addslashes($m['instrument_name'] ?? '') ?>] <?= addslashes($m['user_name']) ?> (理由: <?= addslashes($m['reason'] ?? 'なし') ?>)\n`;
        <?php endforeach; ?>
    <?php endif; ?>

    text += `\n■ 遅刻・早退者:\n`;
    <?php if (empty($status_members['late']) && empty($status_members['early'])): ?>
        text += `・なし\n`;
    <?php else: ?>
        <?php foreach ($status_members['late'] as $m): ?>
            text += `・[遅刻] [<?= addslashes($m['instrument_name'] ?? '') ?>] <?= addslashes($m['user_name']) ?> (<?= addslashes($m['target_time'] ?? '') ?>到着 - <?= addslashes($m['reason'] ?? '') ?>)\n`;
        <?php endforeach; ?>
        <?php foreach ($status_members['early'] as $m): ?>
            text += `・[早退] [<?= addslashes($m['instrument_name'] ?? '') ?>] <?= addslashes($m['user_name']) ?> (<?= addslashes($m['target_time'] ?? '') ?>退出 - <?= addslashes($m['reason'] ?? '') ?>)\n`;
        <?php endforeach; ?>
    <?php endif; ?>

    text += `\n■ 未回答者:\n`;
    <?php if (empty($status_members['unanswered'])): ?>
        text += `・なし\n`;
    <?php else: ?>
        text += `<?= addslashes(implode(', ', array_map(fn($m) => $m['user_name'] . '(' . ($m['instrument_name'] ?? '') . ')', $status_members['unanswered']))) ?>\n`;
    <?php endif; ?>

    navigator.clipboard.writeText(text).then(() => {
        alert('出欠サマリーをクリップボードにコピーしました！\nLINEや黒板の転記にご活用ください。');
    }).catch(() => {
        alert('クリップボードへのコピーに失敗しました。');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
