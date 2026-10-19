<?php

namespace App\Admin;

use App\Libraries\ImageCleaner;
use App\Libraries\SvgSanitizer;
use App\Model\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Settings → Branding (Admin and WebAdmin): the site logo shown in the admin sidebar.
 * Also usable on the website: <img src="{{ media_url(setting('site_logo')) }}">
 */
class Branding extends AdminController
{
    /**
     * Allowed raster logo types: detected MIME type => extension. JPEG/PNG are saved as WebP
     * (cms.convertToWebp). SVG is accepted too, but only after App\Libraries\SvgSanitizer.
     */
    private const TYPES = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private const MAX_KB = 2048;

    /** SVG logos are text; a real logo is far smaller than this. */
    private const SVG_MAX_KB = 512;

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

        if (! is_dir(FCPATH . self::DIR) && ! mkdir(FCPATH . self::DIR, 0755, true)) {
            return redirect()->route('admin.branding')->with('error', lang('Admin.flash.brand_no_write'));
        }

        $mime = $file->getMimeType();

        if ($this->isSvg($file)) {
            if ($file->getSizeByUnit('kb') > self::SVG_MAX_KB) {
                return redirect()->route('admin.branding')->with('error', lang('Admin.flash.brand_svg_too_big'));
            }
            $svg = (new SvgSanitizer())->sanitize((string) file_get_contents($file->getTempName()));
            if ($svg === null) {
                return redirect()->route('admin.branding')->with('error', lang('Admin.flash.brand_svg_invalid'));
            }
            $name = 'logo-' . bin2hex(random_bytes(6)) . '.svg';
            if (file_put_contents(FCPATH . self::DIR . '/' . $name, $svg, LOCK_EX) === false) {
                return redirect()->route('admin.branding')->with('error', lang('Admin.flash.brand_no_write'));
            }
        } else {
            // Checked by the file contents, not by the file name.
            $ext = self::TYPES[$mime] ?? null;
            if ($ext === null || @getimagesize($file->getTempName()) === false) {
                return redirect()->route('admin.branding')->with('error', lang('Admin.flash.brand_type'));
            }

            $name = 'logo-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $file->move(FCPATH . self::DIR, $name);
            // Strips metadata; JPEG/PNG become WebP (the name's extension changes then).
            $name = basename((new ImageCleaner())->clean(FCPATH . self::DIR . '/' . $name, $mime));
        }

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
     * An SVG upload: detected as SVG, or XML/text with a .svg name (servers detect plain SVGs differently).
     * Whether it really is a usable SVG is decided by SvgSanitizer.
     */
    private function isSvg(\CodeIgniter\HTTP\Files\UploadedFile $file): bool
    {
        $mime = $file->getMimeType();

        return $mime === 'image/svg+xml'
            || (in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'image/svg'], true) && strtolower($file->getClientExtension()) === 'svg');
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
