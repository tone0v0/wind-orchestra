// js/calendar.js
// 吹奏楽部 カレンダー & 出欠管理スクリプト

document.addEventListener('DOMContentLoaded', () => {
    let currentYear = new Date().getFullYear();
    let currentMonth = new Date().getMonth() + 1; // 1-12
    let eventsData = [];
    let dailyAttendancesData = [];

    const calendarGrid = document.getElementById('calendar-grid');
    const calendarTitle = document.getElementById('calendar-title');
    const btnPrev = document.getElementById('btn-prev-month');
    const btnNext = document.getElementById('btn-next-month');
    const btnToday = document.getElementById('btn-today');

    // モーダル要素
    const modal = document.getElementById('attendance-modal');
    const btnCloseModal = document.getElementById('btn-close-modal');
    const btnCancelModal = document.getElementById('btn-cancel-modal');
    const attendanceForm = document.getElementById('attendance-form');
    const modalEventTitle = document.getElementById('modal-event-title');
    const modalEventDatetime = document.getElementById('modal-event-datetime');
    const modalEventLocation = document.getElementById('modal-event-location');
    const modalEventDescription = document.getElementById('modal-event-description');
    const formEventId = document.getElementById('form-event-id');
    const formAttendanceDate = document.getElementById('form-attendance-date');
    const formTargetTime = document.getElementById('form-target-time');
    const formReason = document.getElementById('form-reason');
    const groupTargetTime = document.getElementById('group-target-time');
    const groupReason = document.getElementById('group-reason');
    const labelTargetTime = document.getElementById('label-target-time');
    const modalAlert = document.getElementById('modal-alert');
    const modalAdminLink = document.getElementById('modal-admin-link');
    const btnSave = document.getElementById('btn-save-attendance');

    const statusRadios = document.querySelectorAll('input[name="status"]');

    // ステータス表示の定義
    const statusMap = {
        'present': { text: '出席', class: 'badge-present' },
        'late':    { text: '遅刻', class: 'badge-late' },
        'early':   { text: '早退', class: 'badge-early' },
        'absent':  { text: '欠席', class: 'badge-absent' }
    };

    // 初期読み込み
    fetchAndRenderCalendar(currentYear, currentMonth);

    // 月送りナビゲーション
    btnPrev.addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 1) {
            currentMonth = 12;
            currentYear--;
        }
        fetchAndRenderCalendar(currentYear, currentMonth);
    });

    btnNext.addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 12) {
            currentMonth = 1;
            currentYear++;
        }
        fetchAndRenderCalendar(currentYear, currentMonth);
    });

    btnToday.addEventListener('click', () => {
        const today = new Date();
        currentYear = today.getFullYear();
        currentMonth = today.getMonth() + 1;
        fetchAndRenderCalendar(currentYear, currentMonth);
    });

    // イベントデータ取得 & カレンダー描画
    async function fetchAndRenderCalendar(year, month) {
        calendarTitle.textContent = `${year}年 ${month}月`;
        calendarGrid.innerHTML = '<div style="grid-column: span 7; padding: 2rem; text-align: center; color: #64748b;">読み込み中...</div>';

        try {
            const res = await fetch(`api/get_events.php?year=${year}&month=${month}`);
            const data = await res.json();
            if (data.success) {
                eventsData = data.events || [];
                dailyAttendancesData = data.daily_attendances || [];
                renderCalendarGrid(year, month, eventsData, dailyAttendancesData);
            } else {
                calendarGrid.innerHTML = `<div style="grid-column: span 7; padding: 2rem; color: #ef4444; text-align: center;">${data.error || 'データの取得に失敗しました'}</div>`;
            }
        } catch (err) {
            calendarGrid.innerHTML = '<div style="grid-column: span 7; padding: 2rem; color: #ef4444; text-align: center;">通信エラーが発生しました</div>';
        }
    }

    // カレンダーグリッドのHTML構築
    function renderCalendarGrid(year, month, events, dailyAttendances) {
        calendarGrid.innerHTML = '';

        // 曜日ヘッダー
        const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        weekdays.forEach(day => {
            const th = document.createElement('div');
            th.className = 'calendar-weekday';
            th.textContent = day;
            calendarGrid.appendChild(th);
        });

        // 日付計算
        const firstDay = new Date(year, month - 1, 1);
        const lastDay = new Date(year, month, 0);
        const startDayOfWeek = firstDay.getDay(); // 0(日) ~ 6(土)
        const totalDays = lastDay.getDate();

        // 前月末の埋め合わせ
        const prevMonthLastDate = new Date(year, month - 1, 0).getDate();
        for (let i = startDayOfWeek - 1; i >= 0; i--) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day other-month';
            dayDiv.innerHTML = `<span class="day-num">${prevMonthLastDate - i}</span>`;
            calendarGrid.appendChild(dayDiv);
        }

        // 今月の日付
        const todayStr = new Date().toISOString().split('T')[0];

        for (let day = 1; day <= totalDays; day++) {
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day';
            dayDiv.style.cursor = 'pointer'; // 日付マス全体をクリック可能に
            if (dateStr === todayStr) {
                dayDiv.classList.add('today');
            }

            dayDiv.innerHTML = `<span class="day-num">${day}</span>`;

            // その日の練習イベントを抽出
            const dayEvents = events.filter(e => e.event_date === dateStr);
            // その日の日常ステータスを抽出（イベントがない日の出欠）
            const dailyAtt = dailyAttendances.find(d => d.date === dateStr);

            if (dayEvents.length > 0) {
                dayEvents.forEach(evt => {
                    const eventEl = document.createElement('div');
                    eventEl.className = 'calendar-event';

                    let statusBadge = '';
                    if (evt.my_status && statusMap[evt.my_status]) {
                        const st = statusMap[evt.my_status];
                        statusBadge = `<span class="badge ${st.class}">${st.text}</span>`;
                    } else {
                        statusBadge = `<span class="badge badge-none">未回答</span>`;
                    }

                    eventEl.innerHTML = `
                        <span class="event-time">${evt.start_time}〜${evt.end_time}</span>
                        <div class="event-title">${escapeHtml(evt.title)}</div>
                        <div style="margin-top: 0.25rem;">${statusBadge}</div>
                    `;

                    eventEl.addEventListener('click', (e) => {
                        e.stopPropagation();
                        openAttendanceModal(evt);
                    });

                    dayDiv.appendChild(eventEl);
                });
            } else if (dailyAtt) {
                // 練習予定はないが、部員がステータスを入力している場合
                const dailyEl = document.createElement('div');
                dailyEl.className = 'calendar-event';
                dailyEl.style.borderLeftColor = '#64748b';
                dailyEl.style.background = '#f8fafc';

                const st = statusMap[dailyAtt.status] || { text: '予定あり', class: 'badge-none' };
                let timeText = dailyAtt.target_time ? `${dailyAtt.target_time}頃` : '登録済み';
                let reasonText = dailyAtt.reason ? ` - ${escapeHtml(dailyAtt.reason)}` : '';

                dailyEl.innerHTML = `
                    <span class="event-time">${timeText}</span>
                    <div class="event-title" style="font-size: 0.75rem; color: #475569;">出欠予定${reasonText}</div>
                    <div style="margin-top: 0.25rem;">
                        <span class="badge ${st.class}">${st.text}</span>
                    </div>
                `;

                dailyEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openDailyModal(dateStr, dailyAtt);
                });

                dayDiv.appendChild(dailyEl);
            }

            // 日付セル自体のクリック（イベントがない日の出欠登録・変更）
            dayDiv.addEventListener('click', () => {
                if (dayEvents.length === 1) {
                    openAttendanceModal(dayEvents[0]);
                } else if (dayEvents.length > 1) {
                    // 複数ある場合は最初のイベント
                    openAttendanceModal(dayEvents[0]);
                } else {
                    openDailyModal(dateStr, dailyAtt);
                }
            });

            calendarGrid.appendChild(dayDiv);
        }

        // 翌月初の埋め合わせ（7の倍数になるように補完）
        const filledCells = startDayOfWeek + totalDays;
        const remainingCells = (7 - (filledCells % 7)) % 7;
        for (let i = 1; i <= remainingCells; i++) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day other-month';
            dayDiv.innerHTML = `<span class="day-num">${i}</span>`;
            calendarGrid.appendChild(dayDiv);
        }
    }

    // 練習予定がある場合のモーダル表示
    function openAttendanceModal(evt) {
        formEventId.value = evt.id;
        formAttendanceDate.value = evt.event_date;
        modalEventTitle.textContent = evt.title;
        modalEventDatetime.textContent = `${evt.event_date} ${evt.start_time} 〜 ${evt.end_time}`;
        modalEventLocation.textContent = evt.location || '未定';
        modalEventDescription.textContent = evt.description || '特になし';

        if (modalAdminLink) {
            modalAdminLink.style.display = 'inline-flex';
            modalAdminLink.href = `admin/event_detail.php?id=${evt.id}`;
        }

        // 既存の出欠状態をフォームに反映
        const currentStatus = evt.my_status || 'present';
        const radio = document.querySelector(`input[name="status"][value="${currentStatus}"]`);
        if (radio) {
            radio.checked = true;
        }

        formTargetTime.value = evt.my_target_time || '';
        formReason.value = evt.my_reason || '';

        modalAlert.style.display = 'none';
        updateStatusInputsUI(currentStatus);

        modal.classList.add('active');
    }

    // 練習予定がない日の直接出欠登録モーダル
    function openDailyModal(dateStr, existingData) {
        formEventId.value = '';
        formAttendanceDate.value = dateStr;
        modalEventTitle.textContent = `${dateStr} の出欠・予定登録`;
        modalEventDatetime.textContent = `${dateStr}（全体練習の予定はありません）`;
        modalEventLocation.textContent = '個人練習 / 自主練 / 予定';
        modalEventDescription.textContent = '部活動への参加・欠席予定などを自由に登録できます。';

        if (modalAdminLink) {
            modalAdminLink.style.display = 'none';
        }

        const currentStatus = existingData ? existingData.status : 'present';
        const radio = document.querySelector(`input[name="status"][value="${currentStatus}"]`);
        if (radio) {
            radio.checked = true;
        }

        formTargetTime.value = existingData && existingData.target_time ? existingData.target_time : '';
        formReason.value = existingData && existingData.reason ? existingData.reason : '';

        modalAlert.style.display = 'none';
        updateStatusInputsUI(currentStatus);

        modal.classList.add('active');
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    btnCloseModal.addEventListener('click', closeModal);
    btnCancelModal.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // ラジオボタンの切り替え処理
    statusRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            updateStatusInputsUI(radio.value);
        });
    });

    function updateStatusInputsUI(status) {
        // ラベルのスタイル切り替え
        document.querySelectorAll('.status-radio-label').forEach(lbl => {
            lbl.className = 'status-radio-label';
            const r = lbl.querySelector('input');
            if (r.checked) {
                lbl.classList.add(`checked-${r.value}`);
            }
        });

        // 入力項目の出し分け
        if (status === 'present') {
            groupTargetTime.style.display = 'none';
            groupReason.style.display = 'none';
        } else if (status === 'late') {
            groupTargetTime.style.display = 'block';
            labelTargetTime.textContent = '到着予定時刻';
            groupReason.style.display = 'block';
            formReason.placeholder = '例: 3限の授業のため、電車の遅延のため 等';
        } else if (status === 'early') {
            groupTargetTime.style.display = 'block';
            labelTargetTime.textContent = '早退予定時刻';
            groupReason.style.display = 'block';
            formReason.placeholder = '例: アルバイトのため、通院のため 等';
        } else if (status === 'absent') {
            groupTargetTime.style.display = 'none';
            groupReason.style.display = 'block';
            formReason.placeholder = '例: 体調不良のため、冠婚葬祭のため 等';
        }
    }

    // 出欠保存リクエスト送信
    attendanceForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const eventId = formEventId.value;
        const dateStr = formAttendanceDate.value;
        const status = document.querySelector('input[name="status"]:checked')?.value || 'present';
        const targetTime = formTargetTime.value;
        const reason = formReason.value.trim();
        const csrfToken = document.getElementById('form-csrf-token').value;

        btnSave.disabled = true;
        btnSave.textContent = '保存中...';
        modalAlert.style.display = 'none';

        try {
            const res = await fetch('api/save_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    event_id: eventId || null,
                    date: dateStr,
                    status: status,
                    target_time: targetTime,
                    reason: reason
                })
            });

            const data = await res.json();
            if (data.success) {
                closeModal();
                // カレンダーを再取得してバッジを最新化
                await fetchAndRenderCalendar(currentYear, currentMonth);
            } else {
                modalAlert.textContent = data.error || '保存に失敗しました';
                modalAlert.style.display = 'block';
            }
        } catch (err) {
            modalAlert.textContent = '通信エラーが発生しました';
            modalAlert.style.display = 'block';
        } finally {
            btnSave.disabled = false;
            btnSave.textContent = '出欠を保存';
        }
    });

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>'"]/g, tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag));
    }
});
