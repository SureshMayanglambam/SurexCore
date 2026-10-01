<?php

namespace App\Controller;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Base for public website pages: shares site settings with every view,
 * and validates forms with Laravel-style rules (see validateForm()).
 */
abstract class FrontController extends BaseController
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // The website is Japanese: validation messages come from App/Language/ja.
        $request->setLocale('ja');

        // Settings → General → 検索エンジンにインデックスさせない (test / pre-launch sites)
        if (service('installer')->isInstalled() && setting('search_noindex') === '1') {
            $response->setHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    protected function sharedData(): array
    {
        // A 404 can be rendered before installation, when there is no database yet.
        if (! service('installer')->isInstalled()) {
            return ['site' => (object) ['name' => 'SurexCore', 'tagline' => '']];
        }

        return [
            'site' => (object) [
                'name'    => setting('site_name', 'SurexCore'),
                'tagline' => setting('site_tagline', ''),
            ],
        ];
    }

    /**
     * Validate input against field definitions, like Laravel's $request->validate():
     *
     *   'email' => ['label' => 'メールアドレス', 'rules' => 'required|valid_email|max_length[191]'],
     *   'other' => ['label' => 'その他の内容',   'rules' => 'required_if[type,other]|max_length[200]'],
     *   'type'  => ['label' => '種別', 'rules' => 'required', 'messages' => ['required' => '{field}を選択してください。']],
     *
     * Besides CodeIgniter's rules (required, permit_empty, valid_email, max_length[n], matches[field],
     * in_list[a,b], regex_match[/…/], required_with[field], required_without[field], …) you can use:
     *   required_if[field,value1,value2]      required when field has one of the values
     *   required_unless[field,value1,value2]  required unless field has one of the values
     *
     * Errors are then in $this->validator->getErrors() (field => message).
     */
    protected function validateForm(array $fields, ?array $data = null): bool
    {
        $data ??= (array) $this->request->getPost();
        $rules    = [];
        $messages = [];

        foreach ($fields as $name => $field) {
            $rules[$name] = [
                'label' => $field['label'] ?? $name,
                'rules' => $this->resolveConditionalRules($field['rules'] ?? '', $data),
            ];

            // Custom messages per rule, like Laravel. required_if/required_unless become "required".
            foreach ($field['messages'] ?? [] as $rule => $message) {
                $rule = in_array($rule, ['required_if', 'required_unless'], true) ? 'required' : $rule;
                $messages[$name][$rule] = $message;
            }
        }

        return $this->validateData($data, $rules, $messages);
    }

    /**
     * Turn required_if / required_unless into "required" or "permit_empty" for this submission.
     */
    private function resolveConditionalRules(string|array $rules, array $data): array
    {
        // Split on "|" but not inside [...] (regex_match patterns may contain "|").
        $rules = is_array($rules) ? $rules : preg_split('/\|(?![^\[]*\])/', $rules, -1, PREG_SPLIT_NO_EMPTY);
        $out   = [];

        foreach ($rules as $rule) {
            if (is_string($rule) && preg_match('/^(required_if|required_unless)\[(.+)\]$/', $rule, $m)) {
                $params   = array_map('trim', explode(',', $m[2]));
                $other    = dot_array_search(array_shift($params), $data);
                $matches  = is_array($other)
                    ? array_intersect($params, array_map('strval', $other)) !== []
                    : in_array((string) $other, $params, true);
                $required = $m[1] === 'required_if' ? $matches : ! $matches;

                $out[] = $required ? 'required' : 'permit_empty';

                continue;
            }

            $out[] = $rule;
        }

        // Optional fields must not fail their other rules when left empty.
        if (! in_array('required', $out, true) && ! in_array('permit_empty', $out, true)) {
            array_unshift($out, 'permit_empty');
        }

        return array_values(array_unique($out, SORT_REGULAR));
    }
}
