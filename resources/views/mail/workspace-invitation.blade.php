<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="referrer" content="no-referrer">
    <title>Приглашение в пространство «{{ $workspaceName }}»</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;">
        <tr>
            <td style="padding:24px;">
                <h1 style="margin:0 0 12px;font-size:20px;">Приглашение в Landflow</h1>
                <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
                    @if ($inviterName)
                        {{ $inviterName }} приглашает вас
                    @else
                        Вас приглашают
                    @endif
                    в рабочее пространство «{{ $workspaceName }}» с ролью «{{ $roleLabel }}».
                </p>
                <p style="margin:0 0 24px;">
                    <a href="{{ $url }}" style="display:inline-block;padding:12px 20px;background:#111827;color:#ffffff;text-decoration:none;border-radius:6px;font-size:15px;">Принять приглашение</a>
                </p>
                <p style="margin:0 0 8px;color:#555555;font-size:14px;line-height:1.5;">
                    Приглашение действует до {{ $expiresAt }}. Если у вас ещё нет учётной записи Landflow, зарегистрируйтесь на этот адрес электронной почты по ссылке выше.
                </p>
                <p style="margin:0 0 8px;color:#555555;font-size:14px;line-height:1.5;word-break:break-all;">
                    Если кнопка не открывается, скопируйте ссылку в браузер: {{ $url }}
                </p>
                <p style="margin:20px 0 0;color:#888888;font-size:12px;">
                    Если вы не ждали этого письма, просто проигнорируйте его. Письмо отправлено Landflow.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
