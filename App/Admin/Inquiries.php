<?php

namespace App\Admin;

use App\Model\InquiryModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * お問い合わせ: saved form submissions (Admin and WebAdmin).
 * Only while 一般設定 → 「お問い合わせを保存する」 is on; turned off, the menu and these pages are hidden
 * (saved submissions stay in the database).
 */
class Inquiries extends AdminController
{
    public function index(): string
    {
        $this->ensureEnabled();
        $model = model(InquiryModel::class);

        return $this->render('admin.inquiries.index', [
            'items' => $model->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->paginate(20),
            'pager' => $model->pager,
        ]);
    }

    public function show(int $id): string
    {
        $item = $this->find($id);

        if ($item->read_at === null) {
            model(InquiryModel::class)->update($item->id, ['read_at' => date('Y-m-d H:i:s')]);
        }

        return $this->render('admin.inquiries.show', ['item' => $item]);
    }

    /**
     * 未読に戻す
     */
    public function unread(int $id): RedirectResponse
    {
        $item = $this->find($id);
        model(InquiryModel::class)->update($item->id, ['read_at' => null]);

        return redirect()->route('admin.inquiries')->with('success', '未読に戻しました。');
    }

    public function delete(int $id): RedirectResponse
    {
        $item = $this->find($id);
        model(InquiryModel::class)->remove($item);
        log_activity('inquiry.deleted', 'お問い合わせ（' . ($item->name ?: '名前なし') . '・' . $item->created_at . '）を削除しました');

        return redirect()->route('admin.inquiries')->with('success', 'お問い合わせを削除しました。');
    }

    public function attachment(int $id): DownloadResponse
    {
        $item = $this->find($id);
        $path = InquiryModel::FILES . basename((string) $item->attachment_file);

        if (! $item->attachment_file || ! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null)->setFileName($item->attachment_name);
    }

    private function find(int $id): object
    {
        $this->ensureEnabled();

        return model(InquiryModel::class)->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }

    private function ensureEnabled(): void
    {
        if (! InquiryModel::enabled()) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
