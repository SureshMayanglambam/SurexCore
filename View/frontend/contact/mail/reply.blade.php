From: {{ $fromName }} <{{ $fromEmail }}>
Reply-To:
Cc:
Bcc:
Subject: 【{{ $siteName }}】お問い合わせありがとうございます

{{ $data['name'] }} 様

このたびは {{ $siteName }} へお問い合わせいただき、
誠にありがとうございます。

以下の内容で受け付けました。
内容を確認のうえ、担当者より折り返しご連絡いたします。

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

{{ $siteName }}
{{ base_url() }}
