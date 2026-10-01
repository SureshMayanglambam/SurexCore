From: {{ $fromName }} <{{ $fromEmail }}>
Reply-To: {{ $data['email'] }}
Cc:
Bcc:
Subject: 【{{ $siteName }}】お問い合わせがありました

{{ $siteName }} のお問い合わせフォームから送信がありました。

━━━━━━━━━━━━━━━━━━━━━━━━━━
■ご氏名
{{ $data['name'] }}（{{ $data['kana'] }}）

■メールアドレス
{{ $data['email'] }}

■電話番号
{{ $data['tel'] }}

■お問い合わせ内容
{{ $data['message'] }}
━━━━━━━━━━━━━━━━━━━━━━━━━━

送信日時：{{ date('Y-m-d H:i') }}
IPアドレス：{{ service('request')->getIPAddress() }}
