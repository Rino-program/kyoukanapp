# 教室使用管理Webアプリケーション「ClassSpace Manager」開発指示プロンプト

## 1. システム概要・技術スタック

放課後・休日における部活動や有志の空き教室探し・予約・割り振りを円滑に行うための学内Webシステムです。

### 技術スタック
- **フロントエンド (Frontend)**: GitHub Pages 配信
  - HTML5 / Vanilla JavaScript (ES6+) / CSS (Tailwind CSS CDN利用)
  - タブレット端末（iPad, Chromebook, Windowsタブレット）に最適化したレスポンシブUI
- **バックエンド (Backend)**: XServer (PHP 8.x + MySQL / PDO)
  - RESTful JSON API構成
  - GitHub Pagesからのアクセスを許可するCORS設定および安全なAPIトークン認証
- **認証 (Auth)**: 学校配布の Google アカウント（Google Identity Services / OAuth 2.0）
- **通知 (Notification)**: XServer経由のメール送信（PHP mail / PHPMailer） + アプリ内通知

---

## 2. 権限階層と操作マトリクス

権限は以下の6段階で厳密に制御します：
1. **開発者 (developer)**: 全権限 + メタデータ設定、システム設定、DB保守・ログ確認
2. **管理者 (admin)**: メタ以外の全権限（全教室・予約・ユーザー・グループ・スケジュールの強制編集・削除、最大1年先までの優先予約）
3. **先生 (teacher)**: 担当教室の承認、強制割り振り変更、全予約の閲覧
4. **部長 (leader)**: 自部活・配下パートのメンバー管理、定期/通常予約、自動割り振り実行、他グループへの申請
5. **生徒 (student)**: 臨時グループ作成、個人/グループでの空き教室単発予約、相席/譲渡申請、閲覧

### 承認ルール
- **許可制教室の承認**: アナログで指定された「教室の責任者（teacher/admin）」が承認。
- **使用中教室への申請（相席 / 譲渡）**: すでにその枠を取得している「予約者本人」または「責任者」が承認。
- **管理者・開発者**: 上記の承認フローをいつでもオーバーライド（強制変更）可能。

---

## 3. データベース設計 (MySQL DDL)

バックエンドのMySQLにそのまま流し込める完全なテーブル定義です。

```sql
-- ユーザーテーブル
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    role ENUM('developer', 'admin', 'teacher', 'leader', 'student') NOT NULL DEFAULT 'student',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- グループテーブル（部活・階層化パート・臨時グループ）
CREATE TABLE `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('club', 'subgroup', 'temporary') NOT NULL DEFAULT 'temporary',
    parent_id INT NULL,
    leader_user_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES `groups`(id) ON DELETE SET NULL,
    FOREIGN KEY (leader_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- グループ所属テーブル
CREATE TABLE group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('leader', 'member') NOT NULL DEFAULT 'member',
    UNIQUE KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 教室テーブル
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    building VARCHAR(50) NULL,
    capacity INT DEFAULT 40,
    is_special_permission_required BOOLEAN DEFAULT FALSE,
    responsible_user_id INT NULL,
    is_closed_during_class_hours BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 予約・割り振りテーブル
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    group_id INT NULL,
    user_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    reservation_type ENUM('recurring', 'normal', 'event_priority') NOT NULL DEFAULT 'normal',
    status ENUM('active', 'cancelled', 'overridden') NOT NULL DEFAULT 'active',
    purpose VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_time (room_id, start_time, end_time),
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 申請テーブル（相席・譲渡・特別教室許可申請）
CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_type ENUM('permission', 'share', 'transfer') NOT NULL,
    target_reservation_id INT NULL,
    room_id INT NOT NULL,
    applicant_user_id INT NOT NULL,
    approver_user_id INT NULL,
    requested_start_time DATETIME NOT NULL,
    requested_end_time DATETIME NOT NULL,
    message TEXT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (target_reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 通知テーブル
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    link_url VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- システム設定・メタテーブル（開発者・管理者用）
CREATE TABLE system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NULL
);

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('class_hour_start', '08:30', '平日授業開始時刻（予約不可開始）'),
('class_hour_end', '15:30', '平日授業終了時刻（予約可能開始）'),
('general_booking_lead_days', '7', '一般ユーザーの予約可能日数先（7日間）'),
('admin_booking_lead_days', '365', '管理者/開発者の予約可能日数先（1年間）');

4. コア・ビジネスロジック仕様

1.  予約可能期間のバリデーション:
      - student, leader, teacher: 現在日時から +7日 以内の時間のみ予約可能。
      - admin, developer: 現在日時から +365日 以内まで予約可能。
2.  平日授業時間のロック判定:
      - 予約希望日が平日（月〜金）かつ is_closed_during_class_hours = TRUE の教室の場合、08:30〜15:30
        の時間帯を含む予約はシステム側で拒否する。
3.  重複（ダブルブッキング）防止SQL:
    SELECT COUNT(*) FROM reservations 
    WHERE room_id = :room_id 
      AND status = 'active'
      AND start_time < :end_time 
      AND end_time > :start_time;
    ※結果が0件の場合のみ予約作成を許可。
4.  自動割り振り（Auto-Allocation）アルゴリズム:
      - 優先順位:
        1.  定期利用（recurring登録枠）を最優先で確定
        2.  事前予約リクエストの先着順
      - 空き教室の中から条件に合致する部屋へ重複なく自動配置し、未配置のものはアラートを返す。
5.  申請処理（相席/譲渡/許可）と通知:
      - 申請作成時: 承認者（教室責任者または現予約者）の notifications テーブルへ登録 ＋ バックエンドからメール送信。
      - 承認時: 譲渡なら元の予約の status を更新し、新規予約を作成。相席なら同枠に共有フラグを付与。
      - 却下時: 却下理由（未入力時は「理由の記載なしで却下されました」）を添えて申請者に通知＋メール送信。

5. APIエンドポイント設計 (PHP / RESTful)

XServer側の api/ ディレクトリに設置。CORSヘッダー、JSONレスポンス、Bearer Token認証を共通処理として組み込むこと。

  - POST /api/auth/google : GoogleログインIDトークンの検証とJWT/アクセストークン発行
  - GET /api/rooms : 教室一覧取得（責任者名、施錠設定含む）
  - GET /api/reservations?date=YYYY-MM-DD : 指定日の予約状況・空き教室一覧取得
  - POST /api/reservations : 教室予約の作成（重複・時間帯・権限バリデーション）
  - PUT /api/reservations/{id} : 予約の変更/キャンセル（管理者以上は強制変更可）
  - POST /api/reservations/auto-allocate : 自動割り振り実行（部長・管理者用）
  - GET /api/groups : グループ・階層パート一覧取得
  - POST /api/groups : 臨時グループ／パート作成
  - POST /api/requests : 相席・譲渡・特別許可申請の作成（一文メッセージ添付）
  - PUT /api/requests/{id}/approve : 申請の承認
  - PUT /api/requests/{id}/reject : 申請の却下（理由テキスト付き）
  - GET /api/notifications : ログインユーザーの通知一覧取得
  - GET /api/admin/settings : メタ情報・システム設定（開発者・管理者専用）

6. フロントエンドUI/UX要件 (GitHub Pages)

  - デザイン方針:
      - Tailwind CSSを活用したモダンで清潔感のあるUI。
      - 学校配布タブレットでのタッチ操作を第一に考慮し、ボタンやタイムスロットは大きめにタップしやすく配置。
  - 画面構成:
    1.  タイムライン・カレンダー画面（メイン）:
          - 縦軸：教室一覧 / 横軸：時間バー（15分刻み）。
          - 平日授業時間は「授業中（ロック中）」としてグレーアウト。
          - 使用中の枠をタップすると「相席申請」「譲渡申請」ボタンが出現。
          - 空き枠をドラッグまたはタップで即時予約モーダルが開く。
    2.  申請・承認センター:
          - 届いた申請（許可・相席・譲渡）をワンタップで「承認」「却下（理由入力モーダル）」可能。
    3.  部活・パート管理画面:
          - 吹奏楽部等の階層（例：吹奏楽部 > フルートパート）や臨時グループの作成・メンバー追加。
    4.  開発者・管理者専用設定画面:
          - 授業時間ロック帯の変更、年度更新、全データのリセット・一括編集。

7. 実装ステップ指示

GitHub Copilotよ、以下のステップに従って、抜け漏れなく本番で即座に動作するコードを順番に出力してください。

1.  Step 1: バックエンド共通モジュール
      - XServer用の .htaccess（CORS許可・ルーティング設定）
      - config.php（DB接続設定、JWTシークレット、メール送信設定）
      - db.php（PDOシングルトンクラス）
      - auth_middleware.php（Googleトークン検証・権限チェック関数）
      - mailer.php（通知メール送信用関数）
2.  Step 2: APIエンドポイント (PHP)
      - 予約、申請、自動割り振り、通知の主要APIファイル一式
3.  Step 3: フロントエンド実装 (GitHub Pages)
      - index.html（タブレット最適化レイアウト・モーダル群）
      - js/api.js（XServer APIとの通信クライアント）
      - js/auth.js（Google Identity Services ログイン統合）
      - js/timeline.js（タイムラインカレンダー描画・空き状況可視化・ドラッグ/タップ予約）
      - js/app.js（UI制御、申請モーダル、通知バッジ連携）
