<?php
// index.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$current_user = get_current_user_data();
$page_title = 'カレンダー & 出欠管理';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>練習日程 & 出欠確認</h1>
    <p>カレンダーの日付や練習予定をクリックして、出欠の登録・変更を行えます。</p>
</div>

<!-- カレンダーカード -->
<div class="card" style="padding: 1.25rem;">
    <div class="calendar-header">
        <h2 id="calendar-title" style="margin: 0;">2026年 11月</h2>
        <div class="calendar-nav">
            <button type="button" class="btn btn-secondary btn-sm" id="btn-prev-month">◀ 先月</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-today">今日</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-next-month">翌月 ▶</button>
        </div>
    </div>

    <!-- カレンダー本体 -->
    <div class="calendar-grid" id="calendar-grid">
        <!-- JSで曜日および日付セルを描画 -->
    </div>
</div>

<!-- 出欠登録・変更モーダル -->
<div class="modal-overlay" id="attendance-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-event-title">合奏練習</h3>
            <button type="button" class="modal-close" id="btn-close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <!-- 練習情報詳細 -->
            <div style="background: #f8fafc; border-radius: var(--radius); padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.9rem; line-height: 1.6;">
                <div><strong>日時:</strong> <span id="modal-event-datetime">-</span></div>
                <div><strong>場所:</strong> <span id="modal-event-location">-</span></div>
                <div id="modal-event-desc-wrap" style="margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px dashed var(--border);">
                    <strong>内容:</strong> <span id="modal-event-description">-</span>
                </div>
            </div>

            <!-- 出欠登録フォーム -->
            <form id="attendance-form">
                <input type="hidden" id="form-event-id" name="event_id" value="">
                <input type="hidden" id="form-attendance-date" name="date" value="">
                <input type="hidden" id="form-csrf-token" name="csrf_token" value="<?= h(get_csrf_token()) ?>">

                <label style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.5rem; display: block;">
                    あなたの出欠を選択してください <span class="required">*</span>
                </label>

                <!-- 出欠ステータス選択 -->
                <div class="status-selector">
                    <label class="status-radio-label" id="label-status-present">
                        <input type="radio" name="status" value="present" checked>
                        出席
                    </label>
                    <label class="status-radio-label" id="label-status-late">
                        <input type="radio" name="status" value="late">
                        遅刻
                    </label>
                    <label class="status-radio-label" id="label-status-early">
                        <input type="radio" name="status" value="early">
                        早退
                    </label>
                    <label class="status-radio-label" id="label-status-absent">
                        <input type="radio" name="status" value="absent">
                        欠席
                    </label>
                </div>

                <!-- 遅刻・早退の到着/退出予定時刻 -->
                <div class="form-group" id="group-target-time" style="display: none;">
                    <label for="form-target-time" id="label-target-time">到着予定時刻</label>
                    <input type="time" id="form-target-time" name="target_time" class="form-control" style="max-width: 160px;">
                    <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.2rem;">
                        ※何時頃に参加/退出できるか入力してください
                    </small>
                </div>

                <!-- 理由入力欄（欠席・遅刻・早退時） -->
                <div class="form-group" id="group-reason" style="display: none;">
                    <label for="form-reason" id="label-reason">理由・連絡事項</label>
                    <textarea id="form-reason" name="reason" class="form-control" placeholder="例: 3限授業のため、体調不良のため、就職活動のため 等"></textarea>
                </div>

                <div id="modal-alert" class="alert alert-danger" style="display: none; padding: 0.5rem 0.75rem; font-size: 0.85rem;"></div>

                <!-- 管理者用: 全体把握ボタン（上段・中央配置） -->
                <?php if (is_admin()): ?>
                    <div style="text-align: center; margin-top: 1.25rem;">
                        <a href="#" id="modal-admin-link" class="btn btn-secondary btn-sm" target="_blank" style="padding: 0.45rem 1rem;">
                            全体の出欠状況を見る
                        </a>
                    </div>
                <?php endif; ?>

                <!-- 操作ボタン（下段・中央配置） -->
                <div style="display: flex; justify-content: center; align-items: center; gap: 0.75rem; margin-top: 1.25rem;">
                    <button type="button" class="btn btn-secondary" id="btn-cancel-modal" style="min-width: 110px;">キャンセル</button>
                    <button type="submit" class="btn btn-primary" id="btn-save-attendance" style="min-width: 130px;">出欠を保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/calendar.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
