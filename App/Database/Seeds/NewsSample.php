<?php

namespace App\Database\Seeds;

use App\Libraries\ContentSchema;
use App\Model\ContentTypeModel;
use App\Model\EntryModel;
use App\Model\SettingModel;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * php spark db:seed NewsSample
 *
 * Creates the sample content type お知らせ (slug "news") with one field of each common kind and
 * 3 published entries. Run it again to add fields that are missing from an older sample.
 * Used by App/Controller/News.php and View/frontend/news/.
 */
class NewsSample extends Seeder
{
    private const FIELDS = [
        ['name' => 'news_title',    'label' => 'タイトル',     'type' => 'text', 'required' => true, 'show_in_list' => true],
        ['name' => 'news_type',     'label' => '種別',         'type' => 'radio', 'default' => 'info', 'show_in_list' => true,
         'choices' => "info : お知らせ\nevent : イベント\npress : プレスリリース"],
        ['name' => 'news_category', 'label' => 'カテゴリー',   'type' => 'checkbox',
         'choices' => "product : 製品\ncompany : 会社\nrecruit : 採用"],
        ['name' => 'news_pickup',   'label' => 'ピックアップ', 'type' => 'toggle'],
        ['name' => 'news_summary',  'label' => '概要',         'type' => 'textarea', 'rows' => 3],
        ['name' => 'news_image',    'label' => 'アイキャッチ画像', 'type' => 'image'],
        ['name' => 'news_detail',   'label' => '本文',         'type' => 'editor'],
        ['name' => 'news_links',    'label' => '関連リンク',   'type' => 'repeater', 'button_label' => 'リンクを追加',
         'sub_fields' => [
             ['name' => 'link_label', 'label' => 'リンク名', 'type' => 'text'],
             ['name' => 'link_url',   'label' => 'URL',      'type' => 'url'],
         ]],
        ['name' => 'news_related',  'label' => '関連するお知らせ', 'type' => 'relation', 'related_type' => 'news', 'multiple' => true],
    ];

    /** Sample number => sample numbers it is related to (news_related). */
    private const RELATED = [1 => [2, 3], 2 => [1]];

    /** title, type, categories, pickup, summary, detail HTML, links */
    private const SAMPLES = [
        ['ウェブサイトをリニューアルしました', 'info', ['company'], true,
         "ウェブサイトを全面リニューアルしました。\nスマートフォンでも見やすくなりました。",
         '<p>いつもご利用いただきありがとうございます。このたびウェブサイトを全面リニューアルしました。</p><h2>主な変更点</h2><ul><li>スマートフォン対応</li><li>お知らせの検索</li></ul>',
         [['link_label' => 'CodeIgniter 4', 'link_url' => 'https://codeigniter.com/'], ['link_label' => 'SurexCore (GitHub)', 'link_url' => 'https://github.com/SureshMayanglambam/SurexCore']]],
        ['新製品発表会を開催します', 'event', ['product'], false,
         '新製品の発表会を開催します。ぜひご参加ください。',
         '<p>新製品の発表会を開催します。</p><table><tbody><tr><th>日時</th><td>10月20日 14:00〜</td></tr><tr><th>会場</th><td>本社 3F</td></tr></tbody></table>',
         [['link_label' => '会場の地図（Google マップ）', 'link_url' => 'https://maps.google.com/']]],
        ['採用情報を更新しました', 'press', ['company', 'recruit'], false,
         '2027年度の採用情報を公開しました。',
         '<p>2027年度の新卒・中途採用の募集を開始しました。詳しくは採用ページをご覧ください。</p>',
         []],
    ];

    public function run()
    {
        $existing = (new ContentTypeModel())->where('slug', 'news')->first();

        $existing === null ? $this->create() : $this->upgrade($existing);
    }

    private function create(): void
    {
        $errors = [];
        $fields = service('fields')->sanitizeDefinitions(self::FIELDS, $errors);
        if ($errors !== []) {
            CLI::error(implode("\n", $errors));

            return;
        }

        $data = [
            'name'         => 'お知らせ',
            'singular'     => 'お知らせ',
            'slug'         => 'news',
            'description'  => 'サンプル：テキスト・ラジオ・チェックボックス・ON/OFF・テキストエリア・画像・本文・リピーター',
            'icon'         => 'newspaper',
            'fields'       => $fields,
            'title_field'  => 'news_title',
            'sort_order'   => 0,
            'preview_view' => null,
        ];

        service('contentSchema')->sync((object) $data, null);
        $id = (int) (new ContentTypeModel())->insert($data);
        $this->rememberSchema();

        $type   = (new ContentTypeModel())->find($id);
        $author = $this->db->table('users')->select('id')->where('role', 'admin')->orderBy('id')->get()->getRow();

        foreach (self::SAMPLES as $i => [$title, $kind, $categories, $pickup, $summary, $detail, $links]) {
            EntryModel::for($type)->saveEntry([
                'title'        => $title,
                'slug'         => 'news-sample-' . ($i + 1),
                'status'       => 'published',
                'author_id'    => $author->id ?? null,
                'published_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 3 + 1) . ' days')),
            ], [
                'news_title'    => $title,
                'news_type'     => $kind,
                'news_category' => $categories,
                'news_pickup'   => $pickup,
                'news_summary'  => $summary,
                'news_image'    => '',
                'news_detail'   => $detail,
                'news_links'    => $links,
            ]);
        }

        $this->relate($type);
        CLI::write('Created the content type お知らせ (news) with 3 sample entries.', 'green');
    }

    /**
     * An older sample: add the missing fields (existing fields and data stay), fill the sample repeater rows.
     */
    private function upgrade(object $existing): void
    {
        $names   = array_column($existing->fields, 'name');
        $missing = array_values(array_filter(self::FIELDS, static fn ($f) => ! in_array($f['name'], $names, true)));

        if ($missing !== []) {
            $errors = [];
            $fields = service('fields')->sanitizeDefinitions([...$existing->fields, ...$missing], $errors);
            if ($errors !== []) {
                CLI::error(implode("\n", $errors));

                return;
            }

            $new         = clone $existing;
            $new->fields = $fields;
            service('contentSchema')->sync($new, $existing);
            (new ContentTypeModel())->update($existing->id, ['fields' => $fields]);
            $this->rememberSchema();

            CLI::write('Added fields to お知らせ: ' . implode(', ', array_column($missing, 'name')), 'green');
        } else {
            CLI::write('お知らせ already has every sample field.', 'yellow');
        }

        $type = (new ContentTypeModel())->find($existing->id);
        foreach (self::SAMPLES as $i => $sample) {
            $entry = EntryModel::for($type)->where('slug', 'news-sample-' . ($i + 1))->first();

            if ($entry !== null && $sample[6] !== [] && $entry->field('news_links', []) === []) {
                EntryModel::for($type)->saveEntry([], ['news_links' => $sample[6]] + $entry->values(), (int) $entry->id);
            }
        }
        $this->relate($type);
    }

    /**
     * Link the sample entries to each other (news_related), where not set yet.
     */
    private function relate(object $type): void
    {
        $ids = [];
        foreach (array_keys(self::SAMPLES) as $i) {
            $entry = EntryModel::for($type)->where('slug', 'news-sample-' . ($i + 1))->first();
            if ($entry !== null) {
                $ids[$i + 1] = $entry;
            }
        }

        foreach (self::RELATED as $number => $related) {
            $entry = $ids[$number] ?? null;
            if ($entry === null || $entry->field('news_related', []) !== []) {
                continue;
            }
            $values = ['news_related' => array_values(array_filter(array_map(static fn ($n) => isset($ids[$n]) ? (string) $ids[$n]->id : null, $related)))];
            EntryModel::for($type)->saveEntry([], $values + $entry->values(), (int) $entry->id);
        }
    }

    private function rememberSchema(): void
    {
        (new SettingModel())->put('content_schema_hash', ContentSchema::hash((new ContentTypeModel())->allBySlug()));
    }
}
