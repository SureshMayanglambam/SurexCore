<?php

namespace App\Controller;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * Contact form: 入力 (index) → 確認 (confirm) → 完了 (thanks).
 *
 *   GET  /contact          form (posts to /contact/confirm)
 *   POST /contact/confirm  validate → GET /contact/confirm
 *   GET  /contact/confirm  confirmation page
 *   GET  /contact/back     back to the form with the entered values
 *   POST /contact/send     send the mails → /contact/thanks
 *   GET  /contact/thanks   thank-you page
 *
 * Views: View/frontend/contact/{index,confirm,thanks}.blade.php
 * Mails: View/frontend/contact/mail/{admin,reply}.blade.php (plain text; From / Subject / Reply-To at the top)
 */
class Contact extends FrontController
{
    /**
     * Form fields: name => label + rules (Laravel-style, see FrontController::validateForm()).
     */
    protected array $fields = [
        'name'    => ['label' => 'お名前',             'rules' => 'required|max_length[50]'],
        'kana'    => ['label' => 'フリガナ',           'rules' => 'required|max_length[50]|regex_match[/^[ァ-ヶーぁ-ゖ　 ]+$/u]',
                      'messages' => ['regex_match' => '{field}は全角カタカナ又は平仮名で入力してください。']],
        'email'   => ['label' => 'メールアドレス',      'rules' => 'required|valid_email|max_length[191]'],
        'tel'     => ['label' => '電話番号',           'rules' => 'required|regex_match[/^[0-9０-９\\-－ ]{10,15}$/u]',
                      'messages' => ['regex_match' => '{field}は半角数字とハイフンで入力してください（例：080-1234-5678）。']],
        'message' => ['label' => 'お問い合わせ内容',     'rules' => 'required|max_length[2000]'],
        'privacy' => ['label' => '個人情報の取り扱い',   'rules' => 'required',
                      'messages' => ['required' => '個人情報の取り扱いに同意してください。']],
    ];

    /**
     * Choices for selects / radios: value => label (the confirm page and mails show the label).
     * This form has none; add them here when you add a select or radio field.
     */
    protected array $choices = [];

    /** Fields that may contain line breaks. All others are single-line (line breaks removed). */
    protected array $multiline = ['message'];

    /** Session key holding the validated input between the pages. */
    private const SESSION_KEY = 'contact_form';

    /** Sends per IP address within 10 minutes (spam protection). */
    private const MAX_SENDS = 3;

    /**
     * 入力: the form
     */
    public function index(): string
    {
        return $this->render('frontend.contact.index', ['choices' => $this->choices]);
    }

    /**
     * 確認 (/contact/confirm)
     *   POST: the form is submitted here — validate, keep the input, then reload this page as GET
     *         (so refreshing the confirm page never asks to resend the form).
     *   GET:  show what will be sent.
     */
    public function confirm(): RedirectResponse|string
    {
        if ($this->request->is('post')) {
            // Spam bots fill in the hidden honeypot field (added to the form by the "honeypot" route filter):
            // pretend everything is fine and drop the submission.
            if ((string) $this->request->getPost(config('Honeypot')->name) !== '') {
                return redirect()->route('contact');
            }

            if (! $this->validateForm($this->fields)) {
                return redirect()->route('contact')->withInput()->with('errors', $this->validator->getErrors());
            }

            session()->set(self::SESSION_KEY, $this->clean($this->validator->getValidated()));

            return redirect()->route('contact.confirm');
        }

        $data = session(self::SESSION_KEY);

        if (! is_array($data)) {
            return redirect()->route('contact');
        }

        return $this->render('frontend.contact.confirm', [
            'data'    => $data,
            'choices' => $this->choices,
        ]);
    }

    /**
     * 修正する: back to the form with the entered values (old() works in the form).
     */
    public function back(): RedirectResponse
    {
        $data = session(self::SESSION_KEY);

        if (is_array($data)) {
            session()->setFlashdata('_ci_old_input', ['get' => [], 'post' => $data]);
        }

        return redirect()->route('contact');
    }

    /**
     * 送信: mail to the site admin + automatic reply to the sender, then the thanks page.
     */
    public function send(): RedirectResponse
    {
        $data = session(self::SESSION_KEY);

        if (! is_array($data)) {
            return redirect()->route('contact');
        }

        $throttler = service('throttler');
        if (! $throttler->check('contact-' . md5($this->request->getIPAddress()), self::MAX_SENDS, MINUTE * 10)) {
            return redirect()->route('contact.confirm')->with('error', '短時間に送信が集中しています。しばらくしてから再度お試しください。');
        }

        // Recipient of inquiries: cms.adminEmail in .env
        $adminEmail = config('Cms')->adminEmail;
        $mailData   = [
            'data'       => $data,
            'choices'    => $this->choices,
            'siteName'   => setting('site_name', config('Cms')->appName),
            'adminEmail' => $adminEmail,
        ];

        // Plain-text mails; From / Subject / Reply-To are written at the top of each template file.
        $sent = $adminEmail !== '' && send_mail_template($adminEmail, 'frontend.contact.mail.admin', $mailData);

        if (! $sent) {
            log_message('error', 'Contact form: the mail to the admin could not be sent (cms.adminEmail in .env: "{to}").', ['to' => $adminEmail]);

            return redirect()->route('contact.confirm')->with('error', '送信できませんでした。お手数ですが、時間をおいて再度お試しください。');
        }

        // Automatic reply to the address typed into the form. A failure here doesn't fail the inquiry.
        send_mail_template($data['email'], 'frontend.contact.mail.reply', $mailData);

        session()->remove(self::SESSION_KEY);
        session()->setFlashdata('contact_sent', true);

        return redirect()->route('contact.thanks');
    }

    /**
     * 完了: thank-you page (only right after sending)
     */
    public function thanks(): RedirectResponse|string
    {
        if (! session('contact_sent')) {
            return redirect()->route('contact');
        }

        return $this->render('frontend.contact.thanks');
    }

    /**
     * Remove line breaks from single-line fields, so nothing typed into the form can add
     * extra lines (e.g. mail headers) where the value is printed.
     */
    private function clean(array $data): array
    {
        foreach ($data as $name => $value) {
            if (is_string($value) && ! in_array($name, $this->multiline, true)) {
                $data[$name] = trim(preg_replace('/[\r\n\t]+/', ' ', $value));
            }
        }

        return $data;
    }
}
