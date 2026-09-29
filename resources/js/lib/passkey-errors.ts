const messagesByErrorName: Record<string, string> = {
    NotSupportedError: 'Этот браузер не поддерживает ключи доступа.',
    UserCancelledError: 'Операция с ключом доступа отменена.',
    PasskeyExistsError: 'Это устройство уже зарегистрировано как ключ доступа.',
    InvalidDomainError:
        'Ключи доступа нельзя использовать на этом домене. Для локальной разработки используйте localhost.',
};

const fallbackMessage =
    'Не удалось выполнить операцию с ключом доступа. Попробуйте ещё раз.';

const cyrillicPattern = /[А-Яа-яЁё]/;

export function passkeyErrorMessage(error: Error | null): string | null {
    if (!error) {
        return null;
    }

    const knownMessage = messagesByErrorName[error.name];

    if (knownMessage) {
        return knownMessage;
    }

    // Server responses are already localized by Laravel; raw browser or
    // network messages are English and must not reach the UI.
    return cyrillicPattern.test(error.message)
        ? error.message
        : fallbackMessage;
}
