<?php

namespace App\Admin;

use App\Model\MediaModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Media library (メディア) and the upload endpoint for image/file fields and CKEditor
 * (POST /admin/upload?kind=image|file|any). Files go to public/uploads/YYYY/MM/ with random names;
 * the extension comes from the detected MIME type, never from the uploaded file name.
 * Every upload is registered in the library (table wd_media).
 */
class Media extends AdminController
{
    private const KINDS = [
        'image' => [
            'max_kb' => 8192,
            'mimes'  => [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
            ],
        ],
        'file' => [
            'max_kb' => 20480,
            'mimes'  => [
                'application/pdf'                                                           => 'pdf',
                'application/msword'                                                        => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
                'application/vnd.ms-excel'                                                  => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
                'application/vnd.ms-powerpoint'                                             => 'ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'application/zip'                                                           => 'zip',
                'text/plain'                                                                => 'txt',
                'text/csv'                                                                  => 'csv',
                'image/jpeg'                                                                => 'jpg',
                'image/png'                                                                 => 'png',
            ],
        ],
    ];

    /** Files per page in the library. */
    private const PER_PAGE = 40;

    /**
     * メディア: grid of all uploaded files.
     */
    public function index(): string
    {
        $model  = model(MediaModel::class);
        $kind   = in_array($this->request->getGet('kind'), ['image', 'file'], true) ? $this->request->getGet('kind') : null;
        $search = trim((string) $this->request->getGet('q'));

        return $this->render('admin.media.index', [
            'items'  => $model->filter($kind, $search)->paginate(self::PER_PAGE),
            'pager'  => $model->pager,
            'kind'   => $kind,
            'search' => $search,
            'total'  => model(MediaModel::class)->countAllResults(),
        ]);
    }

    /**
     * JSON list for the "メディアから選択" picker in entry forms.
     */
    public function list(): ResponseInterface
    {
        $model  = model(MediaModel::class);
        $kind   = $this->request->getGet('kind') === 'image' ? 'image' : null;   // file fields accept any file
        $search = trim((string) $this->request->getGet('q'));
        $items  = $model->filter($kind, $search)->paginate(self::PER_PAGE);

        return $this->response->setJSON([
            'items' => array_map(fn ($m) => $this->toArray($m), $items),
            'more'  => $model->pager->getCurrentPage() < $model->pager->getPageCount(),
        ]);
    }

    /**
     * JSON details of one file, including where it is used.
     */
    public function show(int $id): ResponseInterface
    {
        $media = $this->findMedia($id);

        return $this->response->setJSON($this->toArray($media) + [
            'usages' => model(MediaModel::class)->usages($media->path),
        ]);
    }

    public function delete(int $id): RedirectResponse
    {
        $media = $this->findMedia($id);

        if (is_file(FCPATH . $media->path)) {
            @unlink(FCPATH . $media->path);
        }
        model(MediaModel::class)->delete($media->id);

        log_activity('media.deleted', '「' . $media->original_name . '」を削除しました（' . $media->path . '）');

        return redirect()->back()->with('success', '「' . $media->original_name . '」を削除しました。');
    }

    public function upload(): ResponseInterface
    {
        $kind   = in_array($this->request->getGet('kind'), ['file', 'any'], true) ? $this->request->getGet('kind') : 'image';
        // "any" (library page): images and documents.
        $config = $kind === 'any'
            ? ['max_kb' => self::KINDS['file']['max_kb'], 'mimes' => self::KINDS['image']['mimes'] + self::KINDS['file']['mimes']]
            : self::KINDS[$kind];
        $file   = $this->request->getFile('upload');

        if ($file === null || ! $file->isValid()) {
            return $this->fail(match ($file?->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがサーバーの上限を超えています。',
                UPLOAD_ERR_PARTIAL                        => 'アップロードが途中で中断されました。もう一度お試しください。',
                null, UPLOAD_ERR_NO_FILE                  => 'ファイルが選択されていません。',
                default                                   => 'ファイルのアップロードに失敗しました。',
            });
        }
        if ($file->getSizeByUnit('kb') > $config['max_kb']) {
            return $this->fail(sprintf('ファイルサイズが大きすぎます（最大%dMB）。', $config['max_kb'] / 1024));
        }

        // Detected from the file contents, not from the browser or the file name.
        $mime = $file->getMimeType();
        $ext  = $config['mimes'][$mime] ?? null;

        if ($ext === null) {
            return $this->fail($kind === 'image'
                ? 'アップロードできる画像はJPG・PNG・GIF・WebPのみです。'
                : 'この種類のファイルはアップロードできません。');
        }
        if (str_starts_with($mime, 'image/') && $kind === 'any' && $file->getSizeByUnit('kb') > self::KINDS['image']['max_kb']) {
            return $this->fail(sprintf('画像のサイズが大きすぎます（最大%dMB）。', self::KINDS['image']['max_kb'] / 1024));
        }
        if (($kind === 'image' || ($kind === 'any' && str_starts_with($mime, 'image/'))) && @getimagesize($file->getTempName()) === false) {
            return $this->fail('正しい画像ファイルではありません。');
        }

        $dir  = 'uploads/' . date('Y/m');
        $name = bin2hex(random_bytes(12)) . '.' . $ext;

        if (! is_dir(FCPATH . $dir) && ! mkdir(FCPATH . $dir, 0755, true)) {
            return $this->fail('アップロード先のフォルダに書き込めません。');
        }

        $file->move(FCPATH . $dir, $name);
        // Images: strip metadata (GPS location, ...) and anything hidden in the file.
        (new \App\Libraries\ImageCleaner())->clean(FCPATH . $dir . '/' . $name, $mime);
        $path = $dir . '/' . $name;
        $id   = model(MediaModel::class)->register($path, $file->getClientName(), (int) current_user()->id);

        log_activity('media.uploaded', '「' . mb_substr($file->getClientName(), 0, 150) . '」をアップロードしました（' . $path . '）');

        return $this->response->setJSON([
            'id'   => $id,
            'url'  => base_url($path),
            'path' => $path,
            'name' => $file->getClientName(),
            'size' => $file->getSize(),
        ]);
    }

    private function findMedia(int $id): object
    {
        return model(MediaModel::class)->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }

    private function toArray(object $m): array
    {
        return [
            'id'       => (int) $m->id,
            'path'     => $m->path,
            'url'      => base_url($m->path),
            'name'     => $m->original_name,
            'mime'     => $m->mime,
            'is_image' => str_starts_with($m->mime, 'image/'),
            'size'     => (int) $m->size,
            'width'    => $m->width !== null ? (int) $m->width : null,
            'height'   => $m->height !== null ? (int) $m->height : null,
            'date'     => date('Y-m-d H:i', strtotime($m->created_at)),
            'exists'   => is_file(FCPATH . $m->path),
        ];
    }

    /**
     * Error format understood by CKEditor's upload adapter and our field script.
     */
    private function fail(string $message): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['error' => ['message' => $message]]);
    }
}
