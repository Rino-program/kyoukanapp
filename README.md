# ClassSpace Manager 取扱説明書

放課後・休日に使用する教室を検索、予約、割り振りするためのWebアプリケーションです。GitHub Pagesで公開する画面、XServerで動かすPHP API、MySQLデータベースで構成されています。

## 1. 構成

```text
ClassSpace Manager
├─ index.html              GitHub Pagesで公開する画面
├─ styles.css              画面デザイン
├─ js/                     API通信、認証、画面操作、タイムライン
└─ api/                    XServer用PHP API、DB定義、共通モジュール
```

## 2. 必要なもの

- PHP 8.xが動くXServerなどのWebサーバー
- MySQLまたはMariaDB
- HTTPSでアクセスできるドメイン
- GitHubアカウントとGitHub Pages
- Google CloudプロジェクトとOAuth 2.0 Webクライアント
- phpMyAdminなどのMySQL操作環境
- FTPまたはXServerファイルマネージャー

## 3. ローカルで確認する

APIを設定していない状態ではデモモードで動作します。`index.html` をブラウザで開くか、VS CodeのLive Serverで配信してください。

外部APIとGoogle認証を明示的に使わず、ローカルだけで確認するにはURL末尾に `?debug=1` を付けます。デバッグモードでは「デバッグユーザー（開発者）」で自動ログインし、予約・グループの変更をブラウザの `localStorage` に保存します。通常モードへ戻すには `?debug=0` を一度開いてください。

デモ画面では、タイムライン上の薄い枠を押すと予約画面が開き、色の付いた予約を押すと相席・譲渡申請画面が開きます。デモデータは実際のデータベースには保存されません。初期状態へ戻す場合は、開発者ツールで `classspace_demo_reservations` と `classspace_demo_groups` を削除してください。

## 4. MySQLを初期化する

1. XServerのサーバーパネルからphpMyAdminを開きます。
2. XServerで作成済みのアプリケーション用データベース（例: `rinoproxs_kyoukanapp`）を左メニューから選択します。
3. 選択したデータベースの「SQL」画面で `api/schema.sql` を貼り付けて実行します。
4. `users`、`rooms`、`groups`、`reservations` などのテーブルを確認します。
5. `rooms` に実際の教室を登録します。

XServerでは通常、データベース作成権限が制限されています。`api/schema.sql` はデータベースを作成したり `USE` したりせず、現在phpMyAdminで選択しているデータベースの中にテーブルを作成します。`#1044`（データベースへのアクセス拒否）が表示された場合は、別のデータベースを作成せず、契約中の対象データベースを選択してから再実行してください。

```sql
INSERT INTO rooms
	(name, building, capacity, is_special_permission_required, is_closed_during_class_hours)
VALUES
	('音楽室', '本館 2F', 40, 0, 1),
	('視聴覚室', '西館 1F', 80, 0, 0),
	('多目的室', '体育館 1F', 100, 0, 0);
```

`is_closed_during_class_hours` が `1` の教室は、平日の08:30から15:30まで予約できません。

## 5. APIを設定する

1. `api/config.example.php` を複製し、ファイル名を `config.php` に変更します。
2. DB接続情報、JWTシークレット、Google Client ID、GitHub PagesのURLを書き換えます。
3. `api/config.php` はGitHubへcommitしません。

```php
'db' => [
	'dsn' => 'mysql:host=localhost;dbname=phpMyAdminで選択したデータベース名;charset=utf8mb4',
	'user' => 'データベースユーザー名',
	'password' => 'データベースパスワード',
],
'jwt_secret' => '十分に長いランダムな文字列',
'google_client_id' => 'Google OAuthクライアントID',
'allowed_origins' => ['https://ユーザー名.github.io'],
'mail' => ['from' => '送信元メールアドレス', 'enabled' => false],
```

`jwt_secret` は推測できない長い文字列にしてください。

## 6. XServerへAPIを配置する

1. XServerの公開ディレクトリ内に `api` フォルダーを作ります。
2. `api/index.php`、`.htaccess`、`lib` フォルダー、作成した `config.php` をアップロードします。
3. `api/.htaccess` のGitHub Pagesユーザー名が `rino-program` になっていることを確認します。
4. `https://rinoproxs.xsrv.jp/kyoukana/api/rooms` にアクセスします。
5. 未認証エラーが返れば、API入口は動作しています。

## 7. Google認証を設定する

1. Google Cloud Consoleでプロジェクトを作成します。
2. OAuth同意画面を設定します。
3. OAuth 2.0のWebクライアントを作成します。
4. 承認済みJavaScript生成元にGitHub PagesのURLを追加します。
5. Client IDを `api/config.php` の `google_client_id` に設定します。
6. 学校アカウントだけに制限する場合は、Google側で学校ドメインの利用制限を設定します。

Google Identity Servicesのログインボタンは画面起動時に表示されます。ログインするとGoogle IDトークンがXServer APIで検証され、ユーザーが `users` に登録されます。初回登録時の権限は `student` です。

## 8. フロントエンドをAPIへ接続する

API URLは `js/api.js` に次の値として実装済みです。

```js
const API_BASE = 'https://rinoproxs.xsrv.jp/kyoukana/api';
```

そのため、iPadごとに開発者ツールで設定する必要はありません。GitHub PagesはPHPを実行できないため、APIはXServerへ配置します。

## 9. GitHub Pagesで公開する

1. GitHubにリポジトリを作成します。
2. `index.html`、`styles.css`、`js` フォルダーをpushします。
3. SettingsのPagesを開き、Sourceに `GitHub Actions` を指定します。
4. `main` ブランチへpushすると、`.github/workflows/pages.yml` が自動実行されます。Actionsタブで `Deploy GitHub Pages` が完了するまで待ちます。
5. 発行されたURLを確認します。リポジトリ名が `kyoukanapp` の場合は `https://rino-program.github.io/kyoukanapp/` です。
6. XServerの `api/config.php` で、GitHub Pagesのオリジンを次のように設定します。パスは含めません。

```php
'allowed_origins' => ['https://rino-program.github.io'],
```

7. XServerの `api/.htaccess` にも `rino-program.github.io` が設定されていることを確認します。
8. GitHub Pagesの画面を開き、Googleログイン、教室一覧、予約一覧、予約作成を順番に確認します。

URLは `https`、独自ドメイン、末尾スラッシュの有無まで一致させてください。

## 10. 初回管理者を設定する

初めてログインしたユーザーは通常 `student` で登録されます。対象ユーザーの学校メールアドレスを確認し、MySQLで権限を変更します。

```sql
UPDATE users SET role = 'admin'
WHERE email = '管理者の学校メールアドレス';
```

権限は `student`、`leader`、`teacher`、`admin`、`developer` の順に強くなります。開発者を設定する場合は `admin` を `developer` に置き換えます。JWTの有効期間は7日間です。

## 11. 画面の使い方

### タイムライン

1. 左右の矢印で日付を切り替えます。
2. 教室行と時刻の交差する空き枠を押します。
3. 教室、開始時刻、終了時刻、用途を入力します。
4. 「予約を確定」を押します。
5. 色付きの予約枠を押すと、相席または譲渡を申請できます。

平日の授業時間帯は灰色で表示されます。API側でも同じ条件を検証するため、画面を経由しない予約も制限されます。

### 申請センター

受信した許可・相席・譲渡申請を確認する画面です。承認者は予約者または教室責任者で、管理者と開発者は代理処理できます。

### グループ管理

臨時グループやパートを作成します。部活動、パート、文化祭などの単位で登録してください。

## 12. メール通知

`api/config.php` で次のように設定すると、申請通知メールを有効にできます。

```php
'mail' => [
	'from' => 'noreply@学校のドメイン',
	'enabled' => true,
],
```

XServerの送信元ドメイン、メール送信制限、迷惑メール判定を確認してください。メール送信が失敗しても、アプリ内通知は登録されます。

## 13. 動作確認チェックリスト

- GitHub Pagesで画面が表示される
- API URLとCORS許可オリジンが正しい
- `rooms` に実際の教室が登録されている
- Googleログイン後に `users` へ登録される
- studentの7日先超過予約が拒否される
- adminの365日先までの予約が許可される
- 平日08:30から15:30を含む予約が拒否される
- 同じ教室・時間帯の二重予約が拒否される
- 申請が承認者へ通知される
- HTTPSでアクセスできる

## 14. トラブルシューティング

### 通信エラーになる

`js/api.js` の `API_BASE` が `https://rinoproxs.xsrv.jp/kyoukana/api` になっていること、XServer上の `index.php` と `.htaccess` の配置を確認します。

### CORSエラーになる

`config.php` の `allowed_origins` と `.htaccess` の許可オリジンを確認します。GitHub PagesのURLと完全一致させてください。

### DB接続エラーになる

DB名、ユーザー名、パスワード、ホスト名を確認し、`schema.sql` を実行済みか確認します。XServer指定のDBホスト名がある場合は `localhost` ではなく指定値を使います。

### 予約できない

予約可能期間、授業時間ロック、既存予約との重複、JWTの有効期限を確認します。

## 15. セキュリティと本番前の確認

- 本番では必ずHTTPSを使用します。
- `config.php` をGitHubへ公開しません。
- DBパスワードとJWTシークレットをログへ出力しません。
- 管理者権限を必要なユーザーだけに付与します。
- XServerとMySQLのバックアップを定期取得します。
- GoogleログインUIが表示され、ログイン後に `users` へ登録されることを確認します。
- 実際の承認・却下・譲渡処理をテスト用アカウントで検証します。
