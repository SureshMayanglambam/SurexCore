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

Version 0.1.2 (developer preview) · Developed by Wonderful Door

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
8. [Generators](#generators)
9. [Content types and custom fields](#content-types-and-custom-fields)
10. [Routing, views and Blade](#routing-views-and-blade)
11. [Frontend assets (Sass / JS)](#frontend-assets-sass--js)
12. [Forms and mail](#forms-and-mail)
13. [Admin panel features](#admin-panel-features)
14. [Configuration (.env)](#configuration-env)
15. [Database changes (automatic migrations)](#database-changes-automatic-migrations)
16. [Deploying to shared hosting](#deploying-to-shared-hosting)
17. [Security](#security)
18. [Feedback](#feedback)

---

## Requirements

- PHP **8.2+** with `intl`, `mbstring`, `mysqli`, `curl`, `fileinfo`, `gd` (and `zip` for backups with uploads)
- MySQL 5.7+ / 8 or MariaDB 10.3+
- Apache with `mod_rewrite` (LiteSpeed works too)

## Get SurexCore

### Start a new project — one command

```bash
composer create-project sureshmayanglambam/surexcore my-site
```

That's it: `my-site/` contains SurexCore with CodeIgniter 4 and BladeOne already in `vendor/`. Then:

```bash
cd my-site
php spark serve          # → http://localhost:8080 — the installer opens
```

The **installer** (in the browser) asks for the database, site name and the first 管理者 account, then creates `.env` with a new encryption key, builds all tables and logs you in.
No command line is needed on the server later — see [Deploying](#deploying-to-shared-hosting).

### Other ways

| Way | Command | `vendor/` |
|---|---|---|
| **Composer** (recommended) | `composer create-project sureshmayanglambam/surexcore my-site` | installed automatically |
| **Release zip** — no Composer at all | download `SurexCore-x.y.z.zip` from [Releases](https://github.com/SureshMayanglambam/SurexCore/releases) and extract it | included |
| **Git clone** — to work on SurexCore itself | `git clone https://github.com/SureshMayanglambam/SurexCore.git my-site && cd my-site && composer install` | after `composer install` |

Without `vendor/` the site can't start; SurexCore then shows a page explaining how to fix it.

## Quick start (Docker)

```bash
composer create-project sureshmayanglambam/surexcore my-site
cd my-site
docker compose up -d
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

1. Create the project inside the web root (`cd /Applications/MAMP/htdocs && composer create-project sureshmayanglambam/surexcore my-site`), or extract the release zip there.
2. Create an empty MySQL database.
3. Open the site in the browser (e.g. `http://localhost:8888/surexcore/`). The installer asks for the database,
   site name and the first admin account, then writes `.env` (with a new encryption key), creates all tables and logs you in.

The installer locks itself afterwards (`writable/installed.lock`). To reinstall from scratch, delete that file and `.env` and empty the database.

## What's in the box

| Area | Features |
|---|---|
| Content | Content types with an ACF-style field builder (16 field types incl. **relation**, groups, repeaters, conditional show/required), a real table per type, SEO fields, drafts, **publish period** (start and optional end), **preview of unsaved changes on the real page**, **duplicate**, **trash with restore**, list columns and date filters |
| Code | Laravel-style models and queries (`NewsModel::published()->latest()->paginate(10)`), `make:cms-model` / `make:cms-controller` generators, a `Page` controller for view-only pages, a sample with every field kind |
| Media | Media library (grid, search, drag & drop upload, where-used, delete), "メディアから選択" in every image/file field, CKEditor 5 image upload |
| Site | Blade frontend you write yourself, contact form (入力 → 確認 → 完了) with **file attachment** and mail templates, automatic `sitemap.xml` / `robots.txt`, noindex switch, maintenance mode |
| Admin | Japanese UI, roles 管理者 / Web管理者, users, branding (logo), activity log, dashboard with charts and disk usage, **お問い合わせ inbox** (optional), **backup download** (.sql or .sql + uploads .zip) |
| Ops | Installer, automatic migrations, hidden admin URL, **one-click deploy packages** in the admin (or `deploy.sh`) |
| Security | Hardened `.htaccess` (no code in uploads, no source/config files served), strict headers + admin CSP, Secure cookies, sanitized editor HTML, metadata-free images — see [Security](#security) |

## Folder structure

```
App/
  Admin/                Admin panel controllers (namespace App\Admin)
  Controller/           Website controllers: Home, News (sample), Contact, Page   ← you work here
  Model/                Models: NewsModel (sample) + EntryModel base             ← and here
  Commands/             spark commands: make:cms-model, make:cms-controller, db:export
  Config/               CodeIgniter config + Cms.php (SurexCore settings), Sitemap.php
  Database/Migrations/  Schema changes, applied automatically
  Database/Seeds/       NewsSample (php spark db:seed NewsSample)
  Entities/             Entry (one row of a content type table)
  Filters/              InstallCheck, Maintenance, AdminAuth, DbUpgrade
  Libraries/            Blade, Fields, ContentSchema, Installer, DatabaseExport, ...
routes/
  web.php               Public website routes                                    ← and here
  admin.php             Admin routes
View/
  frontend/             The website                                               ← and here
    layout/               default (HTML page + SEO/OGP meta), header, footer
    index.blade.php       Top page (the welcome page — replace it)
    news/                 SAMPLE list/detail pages
    contact/              Contact form + mail/ templates
  admin/                Admin panel (AdminLTE 4) and the installer
  system/               404, maintenance, CodeIgniter error pages, pager
public/
  assets/frontend/      Website assets                                            ← and here
    scss/ → css/style.min.css     js/script.js + js/module/ → js/script.min.js
    css/welcome.css, news.css, contact.css (page styles)   lib/ (jQuery, WOW)
  assets/admin/         Admin panel CSS / JS
  assets/vendor/        Bootstrap, AdminLTE, Bootstrap Icons, CKEditor 5, Chart.js, flatpickr (self-hosted)
  uploads/              Uploaded images/files
writable/               Cache, logs, sessions, installed.lock, private uploads (contact attachments)
deploy.sh               Builds upload packages for shared hosting
```

## The sample: route → controller → model → view

The **お知らせ (News)** sample shows the whole pattern and every common field kind. Create it with:

```bash
php spark db:seed NewsSample
```

It creates the content type `news` — fields `news_title` (text), `news_type` (radio), `news_category` (checkbox), `news_pickup` (on/off), `news_summary` (textarea), `news_image` (image), `news_detail` (CKEditor), `news_links` (repeater) and `news_related` (relation) — plus 3 published entries. Running it again on an older sample adds the missing fields.

**Route** — `routes/web.php`
```php
$routes->get('news', [News::class, 'index'], ['as' => 'news']);
$routes->get('news/(:segment)', [News::class, 'detail'], ['as' => 'news.detail']);
```

**Model** — `App/Model/NewsModel.php`: one line, like a Laravel model.
```php
class NewsModel extends EntryModel
{
    protected string $contentTypeSlug = 'news';
}
```

**Controller** — `App/Controller/News.php`
```php
public function index(): string
{
    $posts = NewsModel::published()->latest()->paginate(10);

    return $this->render('frontend.news.index', compact('posts'));
}

public function detail(string $slug): string
{
    $post = NewsModel::published()->where('slug', $slug)->firstOrFail();

    return $this->render('frontend.news.detail', compact('post'));
}
```

**Queries** — static calls start a new query, like Laravel; every CodeIgniter query method chains too:

```php
NewsModel::published()->latest()->paginate(10);                         // + {!! pagination() !!} in the view
NewsModel::published()->where('news_type', 'event')->latest()->limit(3)->get();
NewsModel::published()->whereJsonContains('news_category', 'company')->get();   // checkbox / relation fields
NewsModel::published()->where('slug', $slug)->firstOrFail();             // 404 when not found
NewsModel::query()->where('status', 'draft')->oldest()->get();           // all entries incl. drafts
NewsModel::query()->findOrFail($id);
```

`published()` = status 公開, publish date reached, publish end date (if any) not reached. Scopes: `published`, `latest`, `oldest`, `whereJsonContains`.

**Views** — `View/frontend/news/` show each field kind:

```blade
{{ $post->news_title }}                                   {{-- text --}}
{!! nl2br(esc($post->news_summary)) !!}                   {{-- textarea: escaped, line breaks kept --}}
{{ $post->label('news_type') }}                           {{-- radio / select: label of the stored value --}}
@foreach($post->label('news_category') as $c) … @endforeach  {{-- checkbox: list of labels --}}
@if($post->news_pickup) … @endif                          {{-- on/off: true / false --}}
<img src="{{ media_url($post->news_image) }}">            {{-- image --}}
<div class="ck-content">{!! $post->news_detail !!}</div>  {{-- CKEditor: trusted HTML --}}
@foreach($post->field('news_links', []) as $link)         {{-- repeater: rows as arrays --}}
    <a href="{{ $link['link_url'] }}">{{ $link['link_label'] }}</a>
@endforeach
@foreach($post->relation('news_related') as $related)     {{-- relation: linked entries (published) --}}
    <a href="{{ url_to('news.detail', $related->slug) }}">{{ $related->title }}</a>
@endforeach
```

> Never write `{!! !!}` or `{{ }}` inside a `{{-- comment --}}`: BladeOne reads them before it removes comments.

## Generators

```bash
php spark make:cms-model Store          # App/Model/StoreModel.php  — content type slug "store"
php spark make:cms-controller Store     # App/Controller/Store.php  — uses StoreModel, empty index() / detail()
```

| Option | Example |
|---|---|
| `--slug` | `make:cms-model Shop --slug my-shop` (default: from the name, `ProductItem` → `product-item`) |
| `--model` | `make:cms-controller ProductItem --model Store` |
| `--force` | overwrite an existing file (otherwise existing files are never touched) |

Then create the content type with that slug in the admin, add the routes, and write the views.

## Content types and custom fields

An Admin creates content types under **設定 → コンテンツタイプ**. Each gets a sidebar menu item and list/add/edit screens.

**Field types:** テキスト, テキストエリア, 本文 (CKEditor), 数値, メール, URL, 日付, 画像, ファイル, セレクト, ラジオ, チェックボックス, ON/OFF, **関連付け (relation)**, グループ, リピーター. Groups and repeaters nest up to 3 levels. Fields can be **shown** or **required** only when conditions match (AND within a group, OR between groups), in the form and on the server.

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

**Relation (関連付け)** links entries of another content type (or the same one). In the builder choose the target type and 「複数選択できる」 (off: one entry, on: several). Stored as a JSON list of ids:

```blade
{{ $post->relation('shop')?->title }}                         {{-- single → Entry or null --}}
@foreach($post->relation('tags') as $tag) … @endforeach       {{-- multiple → list of entries --}}
```
```php
NewsModel::published()->whereJsonContains('shop', 5)->get();  // entries linked to entry 5
```

**Publish period:** every entry has 公開日時 and an optional 公開終了日時. Empty end = no end; once the end passes, the entry disappears from the site (lists, detail pages, sitemap, relations).

**Preview template** (コンテンツタイプ → プレビュー用テンプレート): empty = `frontend.{slug}.detail`; a template name for detail pages; or a **page URL like `/recruit`** for list pages — the real controller renders the page with the unsaved entry swapped in.

## Routing, views and Blade

Routes are Laravel-style (`routes/web.php`). Placeholders `(:num)`, `(:segment)`, `(:any)` are passed to the method. Build URLs with `url_to('name', $param)`. Every GET route without placeholders goes into `sitemap.xml` automatically (exclusions in `App/Config/Sitemap.php`).

**Pages without their own controller** (a page that is only a Blade view) need one route line — the URL picks the view:

```php
$routes->get('about', [Page::class, 'show'], ['as' => 'about']);              // View/frontend/about.blade.php (or about/index.blade.php)
$routes->get('company/access', [Page::class, 'show'], ['as' => 'company.access']);
```

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

## Frontend assets (Sass / JS)

The layout loads `css/style.min.css` and `js/script.min.js` (plus jQuery and WOW from `lib/`). Build them from the sources with [Prepros](https://prepros.io) (`prepros.config` is included) or from the command line:

```bash
cd public/assets/frontend
npx sass scss/style.scss css/style.min.css --style=compressed --no-source-map --load-path=scss
npx esbuild js/script.js --bundle --minify --format=iife --outfile=js/script.min.js
```

`js/script.js` imports the modules in `js/module/` and runs them. `base.js` swaps `img.spimg` between `_pc` / `_sp` file names at 768px:

```blade
<img class="spimg" src="{{ asset('assets/frontend/images/top/mv_pc.jpg') }}" alt="">
```

Page-only styles go in their own file, pushed from the view: `@push('styles') <link rel="stylesheet" href="{{ asset('assets/frontend/css/news.css') }}"> @endpush`.

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

Send any template with `send_mail_template($to, 'frontend.contact.mail.admin', $data)`, or HTML with `send_mail($to, $subject, $html)`. Attach files with the option `['attachments' => [[$path, $fileName]]]`.

**File attachment:** the contact form has an optional 添付ファイル (PDF, images, Office, TXT/CSV, ZIP; 5 MB). The type is checked from the file contents; the file waits in `writable/uploads/contact/` (not public), is sent with the admin mail and then deleted.

**お問い合わせ inbox:** 一般設定 → 「お問い合わせを管理画面に保存する」. When on, every sent form is also saved and a 「お問い合わせ」 menu (with an unread count) appears for both roles: list, detail, attachment download, 未読に戻す, delete. Mail is sent either way. Other forms can save into the same inbox:

```php
model(InquiryModel::class)->store('recruit', ['お名前' => $name, …], $name, $email, $attachment);
```

## Admin panel features

| Menu | Who | What |
|---|---|---|
| ダッシュボード | all | stats, charts, recent entries; 管理者 also see activity, system status and **disk usage** |
| (content types) | all | entries: list, add, edit, **preview**, **複製**, delete → **ゴミ箱** (restore / delete for good / empty) |
| お問い合わせ | all | saved form submissions (only while 「お問い合わせを保存する」 is on) |
| メディア | all | media library |
| ブランディング | all | site logo (`setting('site_logo')`) |
| 設定 → 一般設定 | 管理者 | site name, date format, maintenance mode, **検索エンジンにインデックスさせない**, test mail |
| 設定 → ユーザー / コンテンツタイプ / 操作ログ | 管理者 | users and roles, content types, activity log |
| 設定 → バックアップ | 管理者 | download the database (.sql) or database + uploads (.zip); **deploy packages** for the server (初回公開用 / 更新用) |

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

**One click, no commands:** 設定 → バックアップ → 「サーバーにアップロード（デプロイ）」

| Button | Contents |
|---|---|
| 初回公開用（サイト一式） | program + `vendor/` + `public/uploads/` + `_deploy/database.sql` + `_deploy/env-server.txt` (your settings with a **new encryption key**; passwords left as ●●) + `_deploy/README.txt` |
| 更新用（プログラムのみ） | program + `vendor/` — the server's database, uploads and `.env` are untouched |

Only what the server needs is packed (`.htaccess`, `App/`, `View/`, `routes/`, `public/`, `vendor/`, `writable/` folders, `spark`, `composer.*`): never `.env`, logs, cache, sessions, `.git`, `docker/` or your own notes. Extract the zip into the document root, follow `_deploy/README.txt`, then delete `_deploy/`.

**From the command line** (Docker running locally), `deploy.sh` builds the same kind of zip:

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
| **No input file specified.** on every page except the top page | FastCGI/PHP-FPM host + an old `public/.htaccess` → the rewrite rule must be `index.php?/$1` (with `?`), as in SurexCore 0.1.0 |
| `vendor/…/Boot.php` missing | `vendor/` not uploaded → use the release/deploy zip, or `composer install` |
| Images broken after moving a site | `public/uploads/` not copied, or `app.baseURL` still points to the old URL |
| Import error `#3730 Cannot drop table … foreign key` | Old dump format → re-export with `./deploy.sh full` or 設定 → バックアップ (or untick 「外部キーのチェックを有効にする」 in phpMyAdmin) |
| Mail not sent | 設定 → 一般設定 → test mail; SMTP port 465 + `ssl` or 587 + `tls` |

## Security

**Server (`.htaccess`)**
- Only `public/` is reachable; `App/`, `View/`, `routes/` and `writable/` also deny access on their own.
- `public/`: hidden files (`.env`, `.git`, `.htaccess`) and source/config/backup files (`.scss`, `.map`, `.sql`, `.log`, `.ini`, `composer.*`, `prepros.config`, …) return 403; no directory listing.
- `public/uploads/`: PHP and other scripts never run; HTML/SVG/XML/JS/CSS are never served; documents are downloaded (`Content-Disposition: attachment`) with `nosniff`.

**Headers** (`App/Filters/SecurityHeaders.php` + `.htaccess` for static files)
- `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, HSTS on HTTPS; no `X-Powered-By`.
- Admin panel: a strict Content-Security-Policy (own files only), `Cache-Control: no-store`, `X-Robots-Tag: noindex`. The website has no CSP by default — add your own once you know which scripts it loads.

**Application**
- CSRF on every POST/PUT/DELETE (token masked against BREACH); Blade escapes output by default.
- Cookies `HttpOnly`, `SameSite=Lax`, and `Secure` automatically on HTTPS; neutral session cookie name; old session destroyed on login.
- `password_hash()` (rehashed when PHP's default changes), passwords ≥ 10 characters, login rate limiting (5/min per IP), generic login errors.
- Hidden admin URL (`cms.adminPath`); `/install` locked after installation; errors hidden in production.
- Editor (CKEditor) HTML is sanitized on save: no `<script>`, `<iframe>`, forms, SVG, `on…=` handlers or `javascript:`/`data:` links (`App/Libraries/HtmlSanitizer.php`).
- Uploads: type detected from the contents, random file names, no SVG; images are re-encoded — EXIF/GPS metadata and hidden payloads removed, photos turned upright (`App/Libraries/ImageCleaner.php`).
- Contact attachments and saved inquiries are stored outside the web root; downloads only for logged-in admins.
- Activity log of logins (incl. failures) and changes; no plugin system — no third-party plugin code to attack.

**On every live site:** `CI_ENVIRONMENT = production`, HTTPS with `app.forceGlobalSecureRequests = true`, a unique `cms.adminPath`, strong passwords, regular `composer update` and backups.

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
