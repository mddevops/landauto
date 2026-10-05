<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;">
        <tr>
            <td style="padding:24px;">
                <h1 style="margin:0 0 4px;font-size:20px;">Новая заявка</h1>
                <p style="margin:0 0 16px;color:#555555;font-size:14px;">
                    {{ $lead['form'] }} · {{ $lead['site'] }}
                    @if ($lead['site_url'])
                        · {{ $lead['site_url'] }}
                    @endif
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
                    @foreach ($lead['fields'] as $row)
                        <tr>
                            <td style="padding:6px 12px 6px 0;color:#555555;vertical-align:top;width:40%;">{{ $row['label'] }}</td>
                            <td style="padding:6px 0;vertical-align:top;white-space:pre-line;">{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                </table>

                @if ($lead['context'] !== [])
                    <h2 style="margin:20px 0 8px;font-size:16px;">Подробности</h2>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
                        @foreach ($lead['context'] as $row)
                            <tr>
                                <td style="padding:6px 12px 6px 0;color:#555555;vertical-align:top;width:40%;">{{ $row['label'] }}</td>
                                <td style="padding:6px 0;vertical-align:top;word-break:break-all;">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                <p style="margin:20px 0 0;color:#888888;font-size:12px;">
                    Заявка {{ $lead['submission'] }} от {{ $lead['submitted_at'] }}. Письмо отправлено Landflow.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
