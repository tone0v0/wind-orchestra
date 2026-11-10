# 吹奏楽部 出欠確認システム

吹奏楽部の練習日程・出欠管理に特化したWebアプリケーションです。  
MAMP（Apache + PHP + MySQL）環境ですぐに動作します。

---

## 主な機能

1. **カレンダー画面（部員・管理者共通）**
   - 月間カレンダーで練習日程を一目で確認
   - 練習予定をクリックして「出席」「遅刻」「早退」「欠席」をワンタップ登録
   - 「遅刻」「早退」選択時は到着/退出予定時刻と理由を入力可能
   - 「欠席」選択時は欠席理由を入力可能
   - 登録した出欠ステータスがカレンダー上のバッジ（🟢/🟡/🟠/🔴/⚪）にリアルタイム反映

2. **管理者画面（顧問・部長・出欠係専用）**
   - **練習日程の管理**: 練習予定（日時、練習場所、内容など）の新規登録・編集・削除
   - **【吹奏楽特化】パート別出欠集計**: 木管・金管・打楽器など各パートの出席状況を自動集計。合奏が成り立つかを瞬時に判断可能
   - **出欠サマリーのワンクリックコピー**: LINEや連絡網、黒板転記用に欠席・遅刻理由を含めたサマリーテキストを一発コピー
   - **部員名簿 & アカウント発行**: 管理者のみが学生アカウントを作成可能（学生自身の新規登録は不可、パスワード変更は可能）
   - **担当楽器（パート）の割り当て**: Flute, Clarinet, Trumpet など主要パートの登録

---

## ディレクトリ構成

```text
wind-orchestra/
├── config/
│   └── db.php                  # DB接続設定（MAMP自動判別対応）
├── db/
│   └── schema.sql              # データベース定義 & 初期データ
├── includes/
│   ├── auth.php                # 認証・権限管理・CSRF対策
│   ├── header.php              # 共通ヘッダー・ナビゲーション
│   └── footer.php              # 共通フッター
├── css/
│   └── style.css               # レスポンシブデザイン（シンプル＆モダン）
├── js/
│   └── calendar.js             # カレンダー描画・出欠登録モーダル連携
├── api/
│   ├── get_events.php          # カレンダーイベント取得API
│   └── save_attendance.php     # 出欠データ保存API
├── admin/
│   ├── index.php               # 管理者ダッシュボード
│   ├── events.php              # 練習日程の登録・編集
│   ├── event_detail.php        # 練習ごとの出欠詳細 & パート別集計
│   └── users.php               # 部員名簿・アカウント発行
├── index.php                   # メイン画面（カレンダー表示 & 出欠入力）
├── login.php                   # ログイン画面
├── logout.php                  # ログアウト処理
├── change_password.php         # パスワード変更画面
└── README.md
```

---

## 初期アカウント情報

phpMyAdminで `db/schema.sql` をインポートすると、以下のアカウントが利用可能です：

| 権限 | ログインID | 初期パスワード | 氏名 | 担当楽器 |
| :--- | :--- | :--- | :--- | :--- |
| **管理者** | `admin` | `admin123` | 管理者（顧問/部長） | - |
| **部員** | `student01` | `admin123` | 山田 太郎 | B♭ Clarinet (2年) |
| **部員** | `student02` | `admin123` | 佐藤 花子 | Trumpet (1年) |

> ログイン後、画面右上の「PW変更」ボタンからパスワードを変更できます。

---

## MAMPでの起動方法

1. **MAMPの起動**:
   - Apache と MySQL Server を起動します。
2. **フォルダの配置**:
   - 本リポジトリ（`wind-orchestra` フォルダ）を MAMPの `htdocs` 配下に配置します。
     （例: `C:\MAMP\htdocs\wind-orchestra` または シンボリックリンク）
3. **データベースの作成**:
   - ブラウザで phpMyAdmin（`http://localhost/phpmyadmin` または `http://localhost:8888/phpmyadmin`）を開きます。
   - 「インポート」から `wind-orchestra/db/schema.sql` を実行します。
4. **アクセス**:
   - ブラウザで以下のURLを開きます：
     ```text
     http://localhost/wind-orchestra/
     ```
     （MAMP標準ポート8888をお使いの場合は `http://localhost:8888/wind-orchestra/`）
