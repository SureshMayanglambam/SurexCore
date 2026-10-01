```
  ____                               ____
 / ___|  _   _  _ __   ___ __  __   / ___|  ___   _ __   ___
 \___ \ | | | || '__| / _ \\ \/ /  | |     / _ \ | '__| / _ \
  ___) || |_| || |   |  __/ >  <   | |___ | (_) || |   |  __/
 |____/  \__,_||_|    \___|/_/\_\   \____| \___/ |_|    \___|
```

# SurexCore

**A lightweight, secure CMS for shared hosting — CodeIgniter 4 + Blade.**
Build content types in the admin panel, write the website yourself in plain Blade.

Version 0.1.0 (developer preview) · Developed by Wonderful Door

> This is a preview shared for feedback. See [Feedback](#feedback) at the end.

---

## Contents

1. [Requirements](#requirements)
2. [Get SurexCore](#get-surexcore)
3. [Quick start (Docker)](#quick-start-docker)
4. [Quick start (MAMP / XAMPP / shared hosting)](#quick-start-mamp--xampp--shared-hosting)
5. [What's in the box](#whats-in-the-box)
6. [Folder structure](#folder-structure)
7. [The sample: route → controller → model → view](#the-sample-route--controller--model--view)
8. [Content types and custom fields](#content-types-and-custom-fields)
9. [Routing, views and Blade](#routing-views-and-blade)
10. [Forms and mail](#forms-and-mail)
11. [Admin panel features](#admin-panel-features)
12. [Configuration (.env)](#configuration-env)
13. [Database changes (automatic migrations)](#database-changes-automatic-migrations)
14. [Deploying to shared hosting](#deploying-to-shared-hosting)
15. [Security](#security)
16. [Feedback](#feedback)

---

## Requirements

- PHP **8.2+** with `intl`, `mbstring`, `mysqli`, `curl`, `fileinfo`, `gd` (and `zip` for backups with uploads)
- MySQL 5.7+ / 8 or MariaDB 10.3+
- Apache with `mod_rewrite` (LiteSpeed works too)

## Get SurexCore

| | `vendor/` (CodeIgniter) | Next step |
|---|---|---|
| **Release zip** `SurexCore-x.y.z.zip` | ✅ included | Nothing — Composer isn't needed |
| **Git clone** of this repository | ❌ not in Git | `composer install` once (or with Docker: `docker compose exec app composer install`) |

```bash
git clone https://github.com/SureshMayanglambam/SurexCore.git
cd SurexCore
composer install
```

Without `vendor/` the site can't start (PHP reports that `vendor/codeigniter4/framework/system/Boot.php` is missing).

## Quick start (Docker)

```bash
docker compose up -d
docker compose exec app composer install      # only for a Git clone (the zip already has vendor/)
```

| URL | What |
|---|---|
| http://localhost:8095 | Site — the first visit opens the **installer** |
| http://localhost:8095/admin/login | Admin panel |
| http://localhost:8082 | phpMyAdmin |
| http://localhost:8026 | Mailpit (catches every mail sent locally) |

In the installer use: host `db`, port `3306`, database `surexcore`, user `surexcore`, password `surexcore_secret`.
For local mail, set in `.env` afterwards: `email.SMTPHost = 'mail'`, `email.SMTPPort = 1025`, `email.SMTPCrypto = ''`.

Run PHP commands inside the container:

```bash
docker compose exec app php spark routes
docker compose exec app php spark migrate:status
```

The `docker/` folder and `docker-compose.yml` are **optional**: they are only used by `docker compose`. On MAMP, XAMPP or a server they are ignored.

## Quick start (MAMP / XAMPP / shared hosting)

1. Put the project folder in the web root (e.g. `htdocs/surexcore/`). From a Git clone, run `composer install` first.
2. Create an empty MySQL database.
3. Open the site in the browser (e.g. `http://localhost:8888/surexcore/`). The installer asks for the database,
   site name and the first admin account, then writes `.env` (with a new encryption key), creates all tables and logs you in.

The installer locks itself afterwards (`writable/installed.lock`). To reinstall from scratch, delete that file and `.env` and empty the database.

## What's in the box

| Area | Features |
|---|---|
| Content | Content types with an ACF-style field builder (15 field types, groups, repeaters, conditional show/required), a real table per type, SEO fields, drafts and scheduled publishing, **preview of unsaved changes on the real page**, **duplicate entries**, list columns and date filters |
| Media | Media library (grid, search, drag & drop upload, where-used, delete), "メディアから選択" in every image/file field, CKEditor 5 image upload |
| Site | Blade frontend you write yourself, contact form (入力 → 確認 → 完了) with mail templates, automatic `sitemap.xml` / `robots.txt`, noindex switch, maintenance mode |
| Admin | Japanese UI, roles 管理者 / Web管理者, users, branding (logo), activity log, dashboard with charts and disk usage, **backup download** (.sql or .sql + uploads .zip) |
| Ops | Installer, automatic migrations, hidden admin URL, `deploy.sh` packages for shared hosting |

## Folder structure

```
App/
  Admin/                Admin panel controllers (namespace App\Admin)
  Controller/           Website controllers (namespace App\Controller)   ← you work here
  Model/                Models (namespace App\Model)                     ← and here
  Commands/             spark commands (db:export)
  Config/               CodeIgniter config + Cms.php (SurexCore settings), Sitemap.php
  Database/Migrations/  Schema changes, applied automatically
  Entities/             Entry (one row of a content type table)
  Filters/              InstallCheck, Maintenance, AdminAuth, DbUpgrade
  Libraries/            Blade, Fields, ContentSchema, Installer, DatabaseExport, ...
routes/
  web.php               Public website routes                            ← and here
  admin.php             Admin routes
View/
  frontend/             The website                                       ← and here
    layout/               default (HTML page + meta), header, footer
    index.blade.php       Top page (the welcome page — replace it)
    news/                 SAMPLE list/detail pages
    contact/              Contact form + mail/ templates
  admin/                Admin panel (AdminLTE 4) and the installer
  system/               404, maintenance, CodeIgniter error pages, pager
public/
  assets/frontend/      Website CSS / JS / images                         ← and here
  assets/admin/         Admin panel CSS / JS
  assets/vendor/        Bootstrap, AdminLTE, Bootstrap Icons, CKEditor 5, Chart.js, flatpickr (self-hosted)
  uploads/              Uploaded images/files
writable/               Cache, logs, sessions, installed.lock
deploy.sh               Builds upload packages for shared hosting
```

## The sample: route → controller → model → view

The **News (お知らせ)** sample shows the whole pattern. It works once the content type exists:

1. **Admin:** 設定 → コンテンツタイプ → add: name `お知らせ`, slug `news`, fields `headline` (テキスト) and `body` (本文 / CKEditor). Choose `headline` as 「タイトルに使うフィールド」. Add and publish some entries.
2. **Route** — `routes/web.php`
   ```php
   $routes->get('news', [News::class, 'index'], ['as' => 'news']);
   $routes->get('news/(:segment)', [News::class, 'detail'], ['as' => 'news.detail']);
   ```
3. **Model** — `App/Model/NewsModel.php`: one model per content type, like Laravel.
   ```php
   class NewsModel extends EntryModel
   {
       protected string $contentTypeSlug = 'news';

       public function latest(int $limit = 3): array
       {
           return $this->published()->latestPublished()->findAll($limit);
       }
   }
   ```
4. **Controller** — `App/Controller/News.php`
   ```php
   $news = model(NewsModel::class);
   return $this->render('frontend.news.index', [
       'items' => $news->paginated(10),
       'pager' => $news->pager,
   ]);
   ```
5. **View** — `View/frontend/news/index.blade.php`
   ```blade
   @foreach($items as $item)
       <a href="{{ url_to('news.detail', $item->slug) }}">{{ $item->title }}</a>
   @endforeach
   {!! $pager->links() !!}
   ```

Copy these four files for your own pages (Store, Works, FAQ, ...). Delete the sample when you no longer need it.

**Rule of thumb:** the **model** knows *how* to get data (where, order, published-only); the **controller** decides *which* data a page needs; the **view** decides *how it looks*.

## Content types and custom fields

An Admin creates content types under **設定 → コンテンツタイプ**. Each gets a sidebar menu item and list/add/edit screens.

**Field types:** テキスト, テキストエリア, 本文 (CKEditor), 数値, メール, URL, 日付, 画像, ファイル, セレクト, ラジオ, チェックボックス, ON/OFF, グループ, リピーター. Groups and repeaters nest up to 3 levels. Fields can be **shown** or **required** only when conditions match (AND within a group, OR between groups), in the form and on the server.

**Storage — a real table per type:**

| Content type / field | Database |
|---|---|
| Type `store` | table `{prefix}store`: `id, title, slug, status, author_id, meta_title, meta_description, published_at, created_at, updated_at, deleted_at` + one column per field |
| Group `company` → `address` | column `company_address` |
| Repeater `faq` | table `{prefix}store__faq` (`id, parent_id, sort` + sub-fields) |

Saving a type creates/alters its tables. Renaming a field keeps its data; deleting a field drops the column after a confirmation.

**Reading entries:**

```php
entries('store')->where('price <', 1000)->orderBy('price')->paginate(12);   // published only
entry('store', $slug);                                                     // one published entry or null
content_type_exists('store');
```

```blade
{{ $item->title }}
{{ $item->price }}                          {{-- typed: numbers, booleans, arrays --}}
{!! $item->body !!}                         {{-- 本文 (CKEditor) HTML — wrap in class="ck-content" --}}
<img src="{{ media_url($item->photo) }}">   {{-- 画像 --}}
{{ $item->label('color') }}                 {{-- label of a セレクト / ラジオ value --}}
{{ implode(', ', $item->label('tags')) }}   {{-- チェックボックス labels --}}
@foreach($item->field('faq', []) as $row)   {{-- リピーター rows --}}
    {{ $row['question'] }}
@endforeach
{!! nl2br(esc($item->comment)) !!}          {{-- テキストエリア with line breaks, escaped --}}
```

> Use `$item->field('repeater', [])` for repeaters, not `$item->repeater ?? []` (`??` can't see repeater fields).

**Preview template** (コンテンツタイプ → プレビュー用テンプレート): empty = `frontend.{slug}.detail`; a template name for detail pages; or a **page URL like `/recruit`** for list pages — the real controller renders the page with the unsaved entry swapped in.

## Routing, views and Blade

Routes are Laravel-style (`routes/web.php`). Placeholders `(:num)`, `(:segment)`, `(:any)` are passed to the method. Build URLs with `url_to('name', $param)`. Every GET route without placeholders goes into `sitemap.xml` automatically (exclusions in `App/Config/Sitemap.php`).

Pages extend the layout and fill its sections:

```blade
@extends('frontend.layout.default')

@section('title', '会社概要 | ' . $site->name)
@section('description', '…')
@section('robots', 'noindex, nofollow')        {{-- optional --}}
@section('og_image', media_url($item->photo))  {{-- optional --}}
@push('styles') <link rel="stylesheet" href="{{ asset('assets/frontend/css/about.css') }}"> @endpush
@push('scripts') <script src="{{ asset('assets/frontend/js/about.js') }}"></script> @endpush

@section('content')
    …
@endsection
```

- `{{ $x }}` escapes; `{!! $html !!}` outputs raw HTML — only for CKEditor fields, or `{!! nl2br(esc($text)) !!}`.
- `@csrf` in every POST form; `@method('PUT')`, `@error('field') {{ $message }} @enderror`, `old('field')`, `@class([...])`, `@checked()`, `@selected()` work like Laravel.
- Helpers: `$site->name`, `setting('key')`, `asset()`, `url()`, `url_to()`, `url_is('news*')`, `media_url()`, `format_date()`, `ja_date()`, `time_ago()`, `entries()`, `entry()`.
- Always use full view names: `$this->render('frontend.store.detail', [...])`.

| Laravel | SurexCore |
|---|---|
| `route('name', $p)` | `url_to('name', $p)` |
| `config('app.name')` | `$site->name` / `setting('site_name')` |
| `request()->is('news*')` | `url_is('news*')` |
| `@include('partials.x')` | `@include('frontend.layout.x')` (full path under `View/`) |

## Forms and mail

`App/Controller/Contact.php` + `View/frontend/contact/` is a complete 入力 → 確認 → 完了 form: CSRF, honeypot, 3 sends per IP per 10 minutes, an admin mail (to `cms.adminEmail`) and an automatic reply.

Rules are defined in the controller, Laravel-style, including `required_if[field,value]` / `required_unless[...]`:

```php
protected array $fields = [
    'email' => ['label' => 'メールアドレス', 'rules' => 'required|valid_email|max_length[191]'],
    'other' => ['label' => 'その他',        'rules' => 'required_if[type,other]|max_length[100]'],
];
```

Mails are plain-text Blade files (`View/frontend/contact/mail/`) that start with their headers:

```
From: {{ $fromName }} <{{ $fromEmail }}>
Reply-To: {{ $data['email'] }}
Cc:
Bcc:
Subject: 【{{ $siteName }}】お問い合わせがありました

本文…
```

Send any template with `send_mail_template($to, 'frontend.contact.mail.admin', $data)`, or HTML with `send_mail($to, $subject, $html)`.

## Admin panel features

| Menu | Who | What |
|---|---|---|
| ダッシュボード | all | stats, charts, recent entries; 管理者 also see activity, system status and **disk usage** |
| (content types) | all | entries: list, add, edit, **preview**, **複製**, delete |
| メディア | all | media library |
| ブランディング | all | site logo (`setting('site_logo')`) |
| 設定 → 一般設定 | 管理者 | site name, date format, maintenance mode, **検索エンジンにインデックスさせない**, test mail |
| 設定 → ユーザー / コンテンツタイプ / 操作ログ | 管理者 | users and roles, content types, activity log |
| 設定 → バックアップ | 管理者 | download the database (.sql) or database + uploads (.zip) — restore with phpMyAdmin |

## Configuration (.env)

The installer creates `.env` from `.env.example`. Never commit `.env` or copy it between sites.

| Key | Meaning |
|---|---|
| `CI_ENVIRONMENT` | `production` (errors hidden) or `development` (debug toolbar) |
| `app.baseURL` | Site URL with trailing `/` — e.g. `https://example.com/` or `http://localhost:8888/surexcore/` |
| `app.forceGlobalSecureRequests` | `true` to force HTTPS |
| `cms.appName` | Name shown in the admin panel and default mail sender name |
| `cms.adminEmail` | Where contact form mail goes |
| `cms.adminPath` | Admin URL: `https://site/{adminPath}/login`. Use your own word to hide it; `/admin` then shows 404 |
| `database.default.*` | Database connection and table prefix |
| `encryption.key` | Random per site (`php spark key:generate --show`) |
| `email.*` | SMTP for mail. Test it in 設定 → 一般設定 |

## Database changes (automatic migrations)

Add a migration to `App/Database/Migrations/` (`php spark make:migration Name`). After deploying, the next admin page load runs pending migrations automatically — no SSH needed. Content types never need migrations; the admin creates their tables.

## Deploying to shared hosting

SurexCore runs on ordinary shared hosting (tested on Hostinger): PHP 8.2+, MySQL/MariaDB and Apache or LiteSpeed. **No SSH, Composer or Node is needed on the server.**

### 1. Prepare the server

| Check | Where (e.g. Hostinger hPanel) |
|---|---|
| PHP **8.2+**, extensions `intl`, `mbstring`, `mysqli`, `curl`, `fileinfo`, `gd`, `zip` | PHP configuration |
| An **empty database** + user (note name, user, password — usually prefixed, e.g. `u123456789_site`) | Databases → MySQL |
| SSL certificate active on the domain | Security → SSL |
| Document root: can it point to `public/`? | Domains / Websites |

**Document root.** Best: point the domain at the project's `public/` folder. If the host only allows `public_html/` (common), put the **whole project** into `public_html/` — the root `.htaccess` sends every request into `public/`, so `.env`, `App/` and `vendor/` are never reachable from the web.

```
public_html/
├── .htaccess      ← routes everything into public/  (must be uploaded!)
├── .env           ← created by the installer or by you
├── App/  View/  routes/  vendor/  writable/
└── public/        ← index.php, assets, uploads (with its own .htaccess)
```

### 2. Build the upload package

With Docker running locally, `deploy.sh` builds the zip (production `vendor/` included):

```bash
./deploy.sh fresh   # new empty site — the installer runs on the server
./deploy.sh full    # a site you built locally — code + public/uploads + database (.sql) + server .env template
./deploy.sh code    # updates to a live site — code only (the server's content, uploads and .env are kept)
```

Output goes to `deploy/` (`surexcore-{mode}-YYYYmmdd-HHMM.zip`, and for `full` also `database-….sql` and `env-server-….txt`).
**Without Docker:** use the release zip, or zip the project yourself after `composer install --no-dev` (leave out `.env`, `writable/cache|logs|session|debugbar/*` and `deploy/`).

### 3. Upload — always extract on the server

1. Empty `public_html/` (delete the host's `default.php` / `index.html`).
2. Upload the **zip file** with the host's File Manager and **Extract** it there (folder name: `.`, i.e. into `public_html/` itself). Then delete the zip.

> ⚠️ Don't unzip on a Mac/PC and upload the folder: hidden files like `.htaccess` are easily skipped. Without them every page returns **403**. In the File Manager, turn on *Show hidden files* and check `public_html/.htaccess` and `public_html/public/.htaccess` exist.

### 4a. New site (`fresh`) — run the installer

Open `https://your-domain/`. The installer asks for the database (host usually `localhost`), site name and the first 管理者 account, writes `.env` with a new encryption key, creates all tables and locks itself.

### 4b. Site built locally (`full`) — import + .env

1. **Import** `database-….sql` with phpMyAdmin (select the database → インポート). The file replaces every table with your local data, except the activity log (the server keeps its own); tables are dropped in a safe order, so re-importing works too.
2. **Create `.env`** from `env-server-….txt` (it already contains a new encryption key). Easiest: File Manager → *New File* → name it exactly `.env` (not `.env.txt`) in `public_html/` → paste → fill in every `●●`:

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://your-domain.com/'          # https, trailing slash
app.forceGlobalSecureRequests = true
cms.adminEmail = 'info@your-domain.com'
cms.adminPath = 'your-secret-word'                 # admin at /your-secret-word/login, /admin → 404
database.default.hostname = 'localhost'
database.default.database = 'u123456789_site'      # full names from the hosting panel
database.default.username = 'u123456789_user'
database.default.password = '…'
email.SMTPHost = 'smtp.your-host.com'              # e.g. smtp.hostinger.com, port 465 + ssl
```

3. `public/uploads/` is already in the `full` zip. **The database and `public/uploads/` always belong together** — the `.sql` only stores the paths of images.

### 5. After deploying — checklist

- [ ] Top page, a few pages and the 404 page load
- [ ] Log in at `https://your-domain/{adminPath}/login` (opening the admin also runs pending migrations)
- [ ] 設定 → 一般設定 → send a test mail; send the contact form once
- [ ] 設定 → 一般設定 → 「検索エンジンにインデックスさせない」 is **off** (on for test sites)
- [ ] `https://your-domain/sitemap.xml` lists your real domain — submit it in Google Search Console
- [ ] `writable/` is writable (755) — logs appear in `writable/logs/`

### 6. Updating a live site

```bash
./deploy.sh code
```

Upload and extract the zip over `public_html/`. Code, templates and assets are replaced; the server's `.env`, database and `public/uploads/` are untouched. New migrations run on the next admin page load. You can also upload single changed files with the File Manager.

> After go-live **the server database is the source of truth** — clients edit content there. Never import your local database again; take backups from 設定 → バックアップ instead.

### Troubleshooting

| Symptom | Cause → fix |
|---|---|
| **403** on every page | Root `.htaccess` missing (unzipped locally) → extract the zip on the server, check hidden files |
| **500**, log says `Access denied for user ''@'localhost'` | `.env` not read: wrong name/location or `●●` left → `public_html/.env`, exact name `.env` |
| **500**, `Access denied for user 'u…'` | Wrong DB name/user/password → copy them exactly from the hosting panel |
| **500** right after upload | Read `writable/logs/log-YYYY-MM-DD.log`; check PHP is 8.2+ and `writable/` is writable |
| `vendor/…/Boot.php` missing | `vendor/` not uploaded → use the release/deploy zip, or `composer install` |
| Images broken after moving a site | `public/uploads/` not copied, or `app.baseURL` still points to the old URL |
| Import error `#3730 Cannot drop table … foreign key` | Old dump format → re-export with `./deploy.sh full` or 設定 → バックアップ (or untick 「外部キーのチェックを有効にする」 in phpMyAdmin) |
| Mail not sent | 設定 → 一般設定 → test mail; SMTP port 465 + `ssl` or 587 + `tls` |

## Security

- CSRF on every POST/PUT/DELETE; Blade escapes output by default
- `password_hash()`, login rate limiting (5/min per IP), session regeneration on login
- Hidden admin URL (`cms.adminPath`), admin pages are `noindex`
- `/install` locked after installation; uploads checked by content type, random file names
- Activity log of logins (incl. failures) and changes, pruned automatically
- No plugin system — no third-party plugin code to attack

## License

SurexCore is released under the **MIT License** (see `LICENSE`).

Included third-party software keeps its own license: CodeIgniter 4, BladeOne, Bootstrap, AdminLTE, Bootstrap Icons, Chart.js and flatpickr (MIT).
**CKEditor 5** (`public/assets/vendor/ckeditor5/`) is used with its open-source **GPL license key** (`licenseKey: 'GPL'`) and stays under GPL-2.0-or-later.
If you build a closed-source / commercial product on SurexCore, get a commercial CKEditor license or replace the editor.

## Feedback

SurexCore is shared as a developer preview — your feedback decides what comes next. Useful things to report:

- Bugs (what you did, what you expected, what happened, screenshots, `writable/logs/`)
- Anything confusing in the admin panel, the field builder or this README
- Missing features you needed while building a real site
- Hosting environments where installation or deployment failed

Developed by **Wonderful Door**.
