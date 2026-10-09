<?php

namespace App\Admin;

use App\Model\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Settings → Branding (Admin and WebAdmin): the site logo shown in the admin sidebar.
 * Also usable on the website: <img src="{{ media_url(setting('site_logo')) }}">
 */
class Branding extends AdminController
{
    /** Allowed logo types: detected MIME type => extension. (No SVG: it can contain scripts.) */
    private const TYPES = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private const MAX_KB = 2048;

    private const DIR = 'uploads/branding';

    public function index(): string
    {
        return $this->render('admin.branding.index', ['logo' => setting('site_logo', '')]);
    }

    public function update(): RedirectResponse
    {
        $file = $this->request->getFile('logo');

        if ($file === null || ! $file->isValid()) {
            return redirect()->route('admin.branding')->with('error', lang('Admin.flash.brand_pick'));
        }
        if ($file->getSizeByUnit('kb') > self::MAX_KB) {
            return redirect()->route('admin.branding')->with('error', lang('Admin.flash.brand_too_big'));
        }

        // Checked by the file contents, not by the file name.
        $ext = self::TYPES[$file->getMimeType()] ?? null;
        if ($ext === null || @getimagesize($file->getTempName()) === false) {
            return redirect()->route('admin.branding')->with('error', lang('Admin.flash.brand_type'));
        }

        if (! is_dir(FCPATH . self::DIR) && ! mkdir(FCPATH . self::DIR, 0755, true)) {
            return redirect()->route('admin.branding')->with('error', lang('Admin.flash.brand_no_write'));
        }

        $name = 'logo-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $mime = $file->getMimeType();
        $file->move(FCPATH . self::DIR, $name);
        (new \App\Libraries\ImageCleaner())->clean(FCPATH . self::DIR . '/' . $name, $mime);

        $this->removeFile(setting('site_logo', ''));
        model(SettingModel::class)->put('site_logo', self::DIR . '/' . $name);
        log_activity('settings.logo', 'サイトロゴをアップロードしました');

        return redirect()->route('admin.branding')->with('success', lang('Admin.flash.brand_updated'));
    }

    public function delete(): RedirectResponse
    {
        $this->removeFile(setting('site_logo', ''));
        model(SettingModel::class)->put('site_logo', '');
        log_activity('settings.logo', 'サイトロゴを削除しました');

        return redirect()->route('admin.branding')->with('success', lang('Admin.flash.brand_deleted'));
    }

    /**
     * Delete a previous logo file (only files this page created).
     */
    private function removeFile(string $path): void
    {
        if (preg_match('#^' . self::DIR . '/logo-[a-f0-9]{12}\.[a-z]+$#', $path) && is_file(FCPATH . $path)) {
            unlink(FCPATH . $path);
        }
    }
}
