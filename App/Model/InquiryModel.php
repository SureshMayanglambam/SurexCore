<?php

namespace App\Model;

use CodeIgniter\Model;

/**
 * Saved form submissions (お問い合わせ), table wd_inquiries.
 * fields: JSON list of ['label' => 'お名前', 'value' => '…'] in the order of the form.
 */
class InquiryModel extends Model
{
    protected $table         = 'inquiries';
    protected $returnType    = 'object';
    protected $allowedFields = ['form', 'name', 'email', 'fields', 'attachment_name', 'attachment_file', 'ip_address', 'read_at', 'created_at'];
    protected $afterFind     = ['decodeFields'];

    /** Attachments of saved submissions (not reachable from the web). */
    public const FILES = WRITEPATH . 'uploads/inquiries/';

    /**
     * Is saving switched on? (一般設定 → お問い合わせを保存する)
     */
    public static function enabled(): bool
    {
        return setting('store_inquiries') === '1';
    }

    /**
     * Save one submission. $fields: ['label' => value, ...]; $attachment: a file to keep with it.
     */
    public function store(string $form, array $fields, ?string $name, ?string $email, ?array $attachment = null): int
    {
        $file = null;
        if ($attachment && is_file($attachment['path'])) {
            if (! is_dir(self::FILES)) {
                mkdir(self::FILES, 0755, true);
            }
            $file = bin2hex(random_bytes(16)) . '.' . strtolower(pathinfo($attachment['path'], PATHINFO_EXTENSION));
            copy($attachment['path'], self::FILES . $file);
        }

        return (int) $this->insert([
            'form'            => $form,
            'name'            => $name !== null ? mb_substr($name, 0, 100) : null,
            'email'           => $email !== null ? mb_substr($email, 0, 191) : null,
            'fields'          => json_encode(array_map(static fn ($label, $value) => ['label' => $label, 'value' => (string) $value], array_keys($fields), $fields), JSON_UNESCAPED_UNICODE),
            'attachment_name' => $file ? mb_substr($attachment['name'], 0, 191) : null,
            'attachment_file' => $file,
            'ip_address'      => service('request')->getIPAddress(),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function unreadCount(): int
    {
        return $this->where('read_at', null)->countAllResults();
    }

    /**
     * Delete a submission and its attachment.
     */
    public function remove(object $inquiry): void
    {
        if ($inquiry->attachment_file && is_file(self::FILES . basename($inquiry->attachment_file))) {
            @unlink(self::FILES . basename($inquiry->attachment_file));
        }
        $this->delete($inquiry->id);
    }

    protected function decodeFields(array $data): array
    {
        $decode = static function ($row) {
            if (is_object($row) && is_string($row->fields ?? null)) {
                $row->fields = json_decode($row->fields, true) ?: [];
            }

            return $row;
        };
        $data['data'] = $data['singleton'] ? $decode($data['data']) : array_map($decode, $data['data'] ?? []);

        return $data;
    }
}
