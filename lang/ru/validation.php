<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Необходимо принять поле «:attribute».',
    'accepted_if' => 'Необходимо принять поле «:attribute», когда «:other» имеет значение :value.',
    'active_url' => 'Поле «:attribute» должно содержать корректный URL.',
    'after' => 'Поле «:attribute» должно содержать дату после :date.',
    'after_or_equal' => 'Поле «:attribute» должно содержать дату не раньше :date.',
    'alpha' => 'Поле «:attribute» может содержать только буквы.',
    'alpha_dash' => 'Поле «:attribute» может содержать только буквы, цифры, дефисы и подчёркивания.',
    'alpha_num' => 'Поле «:attribute» может содержать только буквы и цифры.',
    'any_of' => 'Поле «:attribute» заполнено некорректно.',
    'array' => 'Поле «:attribute» должно быть массивом.',
    'array_keys' => 'Поле «:attribute» может содержать только следующие ключи: :values.',
    'ascii' => 'Поле «:attribute» может содержать только однобайтовые латинские буквы, цифры и символы.',
    'base64' => 'Поле «:attribute» должно быть корректной строкой Base64.',
    'before' => 'Поле «:attribute» должно содержать дату до :date.',
    'before_or_equal' => 'Поле «:attribute» должно содержать дату не позже :date.',
    'between' => [
        'array' => 'Поле «:attribute» должно содержать от :min до :max элементов.',
        'file' => 'Размер файла в поле «:attribute» должен быть от :min до :max КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть от :min до :max.',
        'string' => 'Поле «:attribute» должно содержать от :min до :max символов.',
    ],
    'boolean' => 'Поле «:attribute» должно иметь значение «да» или «нет».',
    'can' => 'Поле «:attribute» содержит недопустимое значение.',
    'confirmed' => 'Поле «:attribute» не совпадает с подтверждением.',
    'contains' => 'В поле «:attribute» отсутствует обязательное значение.',
    'current_password' => 'Неверный пароль.',
    'date' => 'Поле «:attribute» должно содержать корректную дату.',
    'date_equals' => 'Поле «:attribute» должно содержать дату, равную :date.',
    'date_format' => 'Поле «:attribute» должно соответствовать формату :format.',
    'decimal' => 'Поле «:attribute» должно содержать :decimal знаков после запятой.',
    'declined' => 'Необходимо отклонить поле «:attribute».',
    'declined_if' => 'Необходимо отклонить поле «:attribute», когда «:other» имеет значение :value.',
    'different' => 'Поля «:attribute» и «:other» должны различаться.',
    'digits' => 'Поле «:attribute» должно содержать :digits цифр.',
    'digits_between' => 'Поле «:attribute» должно содержать от :min до :max цифр.',
    'dimensions' => 'Изображение в поле «:attribute» имеет недопустимые размеры.',
    'distinct' => 'Поле «:attribute» содержит повторяющееся значение.',
    'doesnt_contain' => 'Поле «:attribute» не должно содержать следующие значения: :values.',
    'doesnt_end_with' => 'Поле «:attribute» не должно заканчиваться одним из следующих значений: :values.',
    'doesnt_start_with' => 'Поле «:attribute» не должно начинаться с одного из следующих значений: :values.',
    'email' => 'Поле «:attribute» должно содержать корректный адрес электронной почты.',
    'encoding' => 'Поле «:attribute» должно быть в кодировке :encoding.',
    'ends_with' => 'Поле «:attribute» должно заканчиваться одним из следующих значений: :values.',
    'enum' => 'Выбрано недопустимое значение поля «:attribute».',
    'exists' => 'Выбрано недопустимое значение поля «:attribute».',
    'extensions' => 'Файл в поле «:attribute» должен иметь одно из следующих расширений: :values.',
    'file' => 'Поле «:attribute» должно содержать файл.',
    'filled' => 'Поле «:attribute» должно быть заполнено.',
    'gt' => [
        'array' => 'Поле «:attribute» должно содержать больше :value элементов.',
        'file' => 'Размер файла в поле «:attribute» должен быть больше :value КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть больше :value.',
        'string' => 'Поле «:attribute» должно содержать больше :value символов.',
    ],
    'gte' => [
        'array' => 'Поле «:attribute» должно содержать не менее :value элементов.',
        'file' => 'Размер файла в поле «:attribute» должен быть не меньше :value КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть не меньше :value.',
        'string' => 'Поле «:attribute» должно содержать не менее :value символов.',
    ],
    'hex_color' => 'Поле «:attribute» должно содержать корректный цвет в шестнадцатеричном формате.',
    'image' => 'Поле «:attribute» должно содержать изображение.',
    'in' => 'Выбрано недопустимое значение поля «:attribute».',
    'in_array' => 'Значение поля «:attribute» должно присутствовать в «:other».',
    'in_array_keys' => 'Поле «:attribute» должно содержать хотя бы один из следующих ключей: :values.',
    'integer' => 'Поле «:attribute» должно быть целым числом.',
    'ip' => 'Поле «:attribute» должно содержать корректный IP-адрес.',
    'ipv4' => 'Поле «:attribute» должно содержать корректный IPv4-адрес.',
    'ipv6' => 'Поле «:attribute» должно содержать корректный IPv6-адрес.',
    'json' => 'Поле «:attribute» должно содержать корректную JSON-строку.',
    'list' => 'Поле «:attribute» должно быть списком.',
    'lowercase' => 'Поле «:attribute» должно быть в нижнем регистре.',
    'lt' => [
        'array' => 'Поле «:attribute» должно содержать меньше :value элементов.',
        'file' => 'Размер файла в поле «:attribute» должен быть меньше :value КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть меньше :value.',
        'string' => 'Поле «:attribute» должно содержать меньше :value символов.',
    ],
    'lte' => [
        'array' => 'Поле «:attribute» должно содержать не более :value элементов.',
        'file' => 'Размер файла в поле «:attribute» должен быть не больше :value КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть не больше :value.',
        'string' => 'Поле «:attribute» должно содержать не более :value символов.',
    ],
    'mac_address' => 'Поле «:attribute» должно содержать корректный MAC-адрес.',
    'max' => [
        'array' => 'Поле «:attribute» должно содержать не более :max элементов.',
        'file' => 'Размер файла в поле «:attribute» не должен превышать :max КБ.',
        'numeric' => 'Значение поля «:attribute» не должно превышать :max.',
        'string' => 'Поле «:attribute» должно содержать не более :max символов.',
    ],
    'max_digits' => 'Поле «:attribute» должно содержать не более :max цифр.',
    'mimes' => 'Файл в поле «:attribute» должен быть одного из типов: :values.',
    'mimetypes' => 'Файл в поле «:attribute» должен быть одного из типов: :values.',
    'min' => [
        'array' => 'Поле «:attribute» должно содержать не менее :min элементов.',
        'file' => 'Размер файла в поле «:attribute» должен быть не меньше :min КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть не меньше :min.',
        'string' => 'Поле «:attribute» должно содержать не менее :min символов.',
    ],
    'min_digits' => 'Поле «:attribute» должно содержать не менее :min цифр.',
    'missing' => 'Поле «:attribute» должно отсутствовать.',
    'missing_if' => 'Поле «:attribute» должно отсутствовать, когда «:other» имеет значение :value.',
    'missing_unless' => 'Поле «:attribute» должно отсутствовать, если «:other» не имеет значение :value.',
    'missing_with' => 'Поле «:attribute» должно отсутствовать, когда указано :values.',
    'missing_with_all' => 'Поле «:attribute» должно отсутствовать, когда указаны :values.',
    'multiple_of' => 'Значение поля «:attribute» должно быть кратно :value.',
    'not_in' => 'Выбрано недопустимое значение поля «:attribute».',
    'not_regex' => 'Поле «:attribute» имеет некорректный формат.',
    'numeric' => 'Поле «:attribute» должно быть числом.',
    'password' => [
        'letters' => 'Поле «:attribute» должно содержать хотя бы одну букву.',
        'mixed' => 'Поле «:attribute» должно содержать хотя бы одну заглавную и одну строчную букву.',
        'numbers' => 'Поле «:attribute» должно содержать хотя бы одну цифру.',
        'symbols' => 'Поле «:attribute» должно содержать хотя бы один специальный символ.',
        'uncompromised' => 'Значение поля «:attribute» обнаружено в утечке данных. Выберите другое значение.',
    ],
    'present' => 'Поле «:attribute» должно присутствовать.',
    'present_if' => 'Поле «:attribute» должно присутствовать, когда «:other» имеет значение :value.',
    'present_unless' => 'Поле «:attribute» должно присутствовать, если «:other» не имеет значение :value.',
    'present_with' => 'Поле «:attribute» должно присутствовать, когда указано :values.',
    'present_with_all' => 'Поле «:attribute» должно присутствовать, когда указаны :values.',
    'prohibited' => 'Поле «:attribute» запрещено.',
    'prohibited_if' => 'Поле «:attribute» запрещено, когда «:other» имеет значение :value.',
    'prohibited_if_accepted' => 'Поле «:attribute» запрещено, когда поле «:other» принято.',
    'prohibited_if_declined' => 'Поле «:attribute» запрещено, когда поле «:other» отклонено.',
    'prohibited_unless' => 'Поле «:attribute» запрещено, если «:other» не входит в :values.',
    'prohibits' => 'Поле «:attribute» запрещает указывать «:other».',
    'regex' => 'Поле «:attribute» имеет некорректный формат.',
    'required' => 'Поле «:attribute» обязательно для заполнения.',
    'required_array_keys' => 'Поле «:attribute» должно содержать значения для: :values.',
    'required_if' => 'Поле «:attribute» обязательно, когда «:other» имеет значение :value.',
    'required_if_accepted' => 'Поле «:attribute» обязательно, когда поле «:other» принято.',
    'required_if_declined' => 'Поле «:attribute» обязательно, когда поле «:other» отклонено.',
    'required_unless' => 'Поле «:attribute» обязательно, если «:other» не входит в :values.',
    'required_with' => 'Поле «:attribute» обязательно, когда указано :values.',
    'required_with_all' => 'Поле «:attribute» обязательно, когда указаны :values.',
    'required_without' => 'Поле «:attribute» обязательно, когда не указано :values.',
    'required_without_all' => 'Поле «:attribute» обязательно, когда не указано ни одно из значений: :values.',
    'same' => 'Поля «:attribute» и «:other» должны совпадать.',
    'size' => [
        'array' => 'Поле «:attribute» должно содержать :size элементов.',
        'file' => 'Размер файла в поле «:attribute» должен быть :size КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть равно :size.',
        'string' => 'Поле «:attribute» должно содержать :size символов.',
    ],
    'starts_with' => 'Поле «:attribute» должно начинаться с одного из следующих значений: :values.',
    'string' => 'Поле «:attribute» должно быть строкой.',
    'timezone' => 'Поле «:attribute» должно содержать корректный часовой пояс.',
    'unique' => 'Такое значение поля «:attribute» уже используется.',
    'uploaded' => 'Не удалось загрузить файл в поле «:attribute».',
    'uppercase' => 'Поле «:attribute» должно быть в верхнем регистре.',
    'url' => 'Поле «:attribute» должно содержать корректный URL.',
    'ulid' => 'Поле «:attribute» должно содержать корректный ULID.',
    'uuid' => 'Поле «:attribute» должно содержать корректный UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'Имя',
        'email' => 'Электронная почта',
        'password' => 'Пароль',
        'current_password' => 'Текущий пароль',
        'password_confirmation' => 'Подтверждение пароля',
        'token' => 'Токен',
        'remember' => 'Запомнить меня',
    ],

];
