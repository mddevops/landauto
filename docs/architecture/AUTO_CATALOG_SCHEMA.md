# Каталог автомобилей — согласованная схема

Версия: 2. Дата: 04.10.2026. MySQL 8 / Laravel.

## Назначение

Отдельный общий справочник для выбора автомобиля при создании машины или живого предложения. Цепочка выбора: марка → модель → поколение → серия → модификация → комплектация.

Справочник содержит технические сведения и заводское оснащение. VIN, пробег, цена продажи, наличие и фотографии конкретного автомобиля относятся к отдельной базе предложений. Подготовленные изображения по цветам можно добавить позднее отдельным модулем.

Это собственная схема, согласованная для проекта, а не внутренняя структура базы Auto.ru. Предыдущий вариант с конфигурациями заменён этой простой моделью.

## Общие правила

- Во всех десяти таблицах: id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY; created_at, updated_at DATETIME (Laravel может создавать nullable timestamps).
- Все внешние ключи: BIGINT UNSIGNED с реальным FOREIGN KEY.
- status: BOOLEAN NOT NULL DEFAULT 1; 1 — активна, 0 — выключена.
- sort_order: INT UNSIGNED NOT NULL DEFAULT 0; сортировка по sort_order, затем id.
- Строки: utf8mb4. NULL означает отсутствие сведений.
- url — сегмент адреса, например audi, а не полный URL. Использовать латиницу и дефисы, без слешей.
- name обязательное; name_ru необязательное.
- Даты выпуска: SMALLINT UNSIGNED; year_to=NULL означает продолжающийся выпуск или неизвестный конец. Для точного различения этих случаев потребуется дополнительное поле позднее.
- Индексировать все FK. Основные сущности не удалять при наличии потомков: ON DELETE RESTRICT; скрывать через status.

## 1. Марки — auto_marks

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| name | VARCHAR(255) | NOT NULL | Audi |
| name_ru | VARCHAR(255) | NULL | Ауди |
| url | VARCHAR(160) | NOT NULL | audi |
| logo_min | VARCHAR(1024) | NULL | Путь маленького логотипа |
| logo_big | VARCHAR(1024) | NULL | Путь большого логотипа |
| country | VARCHAR(100) | NULL | Страна происхождения марки |
| status | BOOLEAN | DEFAULT 1 | Активность |
| sort_order | INT UNSIGNED | DEFAULT 0 | Сортировка |

UNIQUE(url). country не означает страну сборки конкретной машины.

## 2. Модели — auto_models

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| mark_id | BIGINT UNSIGNED | NOT NULL, FK auto_marks.id | Марка |
| name | VARCHAR(255) | NOT NULL | A6 |
| name_ru | VARCHAR(255) | NULL | Русское название |
| url | VARCHAR(160) | NOT NULL | a6 |
| class | VARCHAR(32) | NULL | Класс автомобиля |
| year_from | SMALLINT UNSIGNED | NULL | Начало выпуска модели |
| year_to | SMALLINT UNSIGNED | NULL | Конец выпуска |
| parent_id | BIGINT UNSIGNED | NULL, FK auto_models.id | Родитель для группировки моделей |
| status | BOOLEAN | DEFAULT 1 | Активность |
| sort_order | INT UNSIGNED | DEFAULT 0 | Сортировка |

UNIQUE(mark_id, url). parent_id не относится к поколениям: это необязательная группировка моделей. В приложении запрещать ссылку на себя, циклы и родителя другой марки. Если группировка не используется, parent_id везде NULL.

## 3. Поколения — auto_generations

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| model_id | BIGINT UNSIGNED | NOT NULL, FK auto_models.id | Модель |
| name | VARCHAR(255) | NOT NULL | VI (C9) |
| url | VARCHAR(160) | NOT NULL | vi-c9 |
| year_from | SMALLINT UNSIGNED | NULL | Начало выпуска |
| year_to | SMALLINT UNSIGNED | NULL | Конец выпуска |
| status | BOOLEAN | DEFAULT 1 | Активность |
| sort_order | INT UNSIGNED | DEFAULT 0 | Сортировка |

UNIQUE(model_id, url). Рестайлинг — отдельная запись, например V (C8) Рестайлинг, url= v-c8-restyling. Не включать годы в name: подпись с периодом формировать из колонок.

## 4. Серии — auto_series

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| generation_id | BIGINT UNSIGNED | NOT NULL, FK auto_generations.id | Поколение |
| name | VARCHAR(255) | NOT NULL | Седан / Седан L / Универсал 5 дв. |
| url | VARCHAR(160) | NOT NULL | sedan / sedan-l |
| image | VARCHAR(1024) | NULL | Главное изображение серии |
| status | BOOLEAN | DEFAULT 1 | Активность |
| sort_order | INT UNSIGNED | DEFAULT 0 | Сортировка |

UNIQUE(generation_id, url). model_id не дублируется: определяется через generation_id.

auto_body_types в этой версии не создаётся. Серия — конкретный кузов в поколении. Для будущего общего фильтра по типам кузова можно добавить справочник и body_type_id в auto_series; Седан и Седан L тогда будут двумя сериями одного типа.

## 5. Модификации — auto_modifications

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| series_id | BIGINT UNSIGNED | NOT NULL, FK auto_series.id | Серия |
| name | VARCHAR(255) | NOT NULL | Название технической версии |
| engine_volume | INT UNSIGNED | NULL | Точный объём двигателя в см³ |
| engine_power | DECIMAL(8,2) UNSIGNED | NULL | Основная мощность версии, л.с. |
| engine | VARCHAR(32) | NULL | Нормализованный тип силовой установки |
| transmission | VARCHAR(32) | NULL | Тип коробки |
| drive | VARCHAR(16) | NULL | Тип привода |
| consumption_100_km | DECIMAL(8,3) UNSIGNED | NULL | Смешанный расход, л/100 км |
| acceleration_0_100 | DECIMAL(6,2) UNSIGNED | NULL | Разгон 0–100 км/ч, с |
| status | BOOLEAN | DEFAULT 1 | Активность |
| sort_order | INT UNSIGNED | DEFAULT 0 | Сортировка |

Рекомендуемые собственные коды:

| Колонка | Коды |
|---|---|
| engine | petrol, diesel, hybrid_petrol, hybrid_diesel, electric, gas, other |
| transmission | manual, automatic, cvt, robot, reducer, other |
| drive | fwd, rwd, awd |

Коды преобразуются в русские подписи в интерфейсе. Подтип гибрида, топливо и мощности отдельных двигателей при необходимости сохраняются отдельными характеристиками. Для гибридов engine_power означает основную заявленную мощность версии; не складывать мощности самостоятельно.

Пример engine_volume=2995: на экране показывается 3.0 л. Для электромобилей engine_volume и расход в литрах — NULL; расход электроэнергии хранится характеристикой с единицей кВт·ч/100 км. Не подставлять 0 вместо неизвестного значения.

Название, объём и мощность не уникальны: технические версии могут иметь одинаковые краткие подписи. Не создавать UNIQUE(series_id, name). Сопоставление при импорте должно использовать проверенные внешние идентификаторы; механика импорта проектируется отдельно.

## 6. Комплектации — auto_equipments

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| modification_id | BIGINT UNSIGNED | NOT NULL, FK auto_modifications.id | Модификация |
| name | VARCHAR(255) | NOT NULL | Base / edition one |
| status | BOOLEAN | DEFAULT 1 | Активность |
| sort_order | INT UNSIGNED | DEFAULT 0 | Сортировка |

series_id не дублируется: определяется через modification_id. auto_configurations не требуется — сама запись комплектации представляет сочетание технической версии и уровня оснащения.

Одинаковая комплектация у двух модификаций — две записи с разными modification_id. Оснащение и характеристики сохраняются отдельно для каждой записи. Создавать только реальные сочетания, а не все возможные комбинации.

Не ставить глобальную уникальность на name. Даже UNIQUE(modification_id, name) допустим только если гарантирована одна редакция комплектации: рынки и годы могут отличаться. Эта простая версия не имеет отдельной модели рынков и редакций; такие варианты нельзя молча объединять. Если они нужны, сначала расширить схему соответствующими полями.

## 7. Характеристики — auto_characteristics

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| code | VARCHAR(100) | NOT NULL, UNIQUE | Стабильный машинный код |
| name | VARCHAR(255) | NOT NULL | Название группы или параметра |
| parent_id | BIGINT UNSIGNED | NULL, FK auto_characteristics.id | Родительская группа |
| unit | VARCHAR(32) | NULL | Единица параметра |
| sort_order | INT UNSIGNED | DEFAULT 0 | Сортировка |

Два уровня: корневые строки parent_id=NULL — группы, дочерние — параметры. Для группы unit=NULL. Запрещать циклы и вложенность глубже двух уровней; значение можно привязать только к параметру. code уникален и для групп, и для параметров.

| code | name | Родитель | unit |
|---|---|---|---|
| dimensions | Размеры | NULL | NULL |
| length | Длина | dimensions | мм |
| width | Ширина | dimensions | мм |
| height | Высота | dimensions | мм |
| wheelbase | Колёсная база | dimensions | мм |
| chassis | Подвеска и тормоза | NULL | NULL |
| front_suspension | Передняя подвеска | chassis | NULL |

Это пример словаря, а не полный список параметров.

## 8. Значения характеристик — auto_characteristic_values

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| equipment_id | BIGINT UNSIGNED | NOT NULL, FK auto_equipments.id | Комплектация конкретной модификации |
| characteristic_id | BIGINT UNSIGNED | NOT NULL, FK auto_characteristics.id | Параметр |
| value | TEXT | NOT NULL | Значение без единицы измерения |

UNIQUE(equipment_id, characteristic_id). unit здесь не хранится: берётся из определения параметра. Примеры value: 4999 или Независимая, пружинная. Отсутствие данных — отсутствие строки, не пустая строка или тире.

TEXT позволяет начать с простой схемы и хранить числовые и словесные параметры. Числа сохранять канонически с точкой, без разделителей тысяч. Для числовой сортировки и диапазонных фильтров по этим параметрам потребуется типизированное поле; сравнивать TEXT как число без отдельного решения не следует. Базовые фильтры мощности, объёма, расхода и разгона используют числовые поля модификации.

Не заводить независимо редактируемые дубликаты основных полей: мощность и объём читаются из модификации, страна из марки, класс из модели. Дополнительные характеристики хранятся здесь. unit должен быть единым для всех значений параметра; входящие значения конвертируются при импорте.

## 9. Опции — auto_options

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| code | VARCHAR(100) | NOT NULL, UNIQUE | Стабильный код |
| name | VARCHAR(255) | NOT NULL | Группа или опция |
| parent_id | BIGINT UNSIGNED | NULL, FK auto_options.id | Родительская группа |
| sort_order | INT UNSIGNED | DEFAULT 0 | Сортировка |

Те же правила двух уровней: группа → опция; запрещать циклы. В auto_option_values привязывать только дочерние опции.

| code | name | Родитель |
|---|---|---|
| safety | Безопасность | NULL |
| abs | ABS | safety |
| comfort | Комфорт | NULL |
| heated_front_seats | Подогрев передних сидений | comfort |

## 10. Опции комплектации — auto_option_values

| Колонка | Тип | NULL / значение по умолчанию | Значение |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | ID |
| option_id | BIGINT UNSIGNED | NOT NULL, FK auto_options.id | Опция |
| equipment_id | BIGINT UNSIGNED | NOT NULL, FK auto_equipments.id | Комплектация |
| is_base | BOOLEAN | NOT NULL, без DEFAULT | Включена или доступна за доплату |

UNIQUE(equipment_id, option_id).

- is_base=1 — входит в стандартное оснащение.
- is_base=0 — доступна за доплату.
- Нет записи — сведения не установлены; это не доказательство отсутствия опции.

is_base задавать явно: отсутствие DEFAULT предотвращает случайное объявление опции стандартной. Если источник перечисляет опцию без объяснения статуса, не угадывать is_base. Для хранения неизвестного или явно недоступного статуса позднее заменить поле на availability: standard / optional / unavailable / unknown.

## Связи и индексы

| Дочерняя таблица | Поле | Родитель |
|---|---|---|
| auto_models | mark_id | auto_marks.id |
| auto_models | parent_id | auto_models.id |
| auto_generations | model_id | auto_models.id |
| auto_series | generation_id | auto_generations.id |
| auto_modifications | series_id | auto_series.id |
| auto_equipments | modification_id | auto_modifications.id |
| auto_characteristics | parent_id | auto_characteristics.id |
| auto_characteristic_values | equipment_id | auto_equipments.id |
| auto_characteristic_values | characteristic_id | auto_characteristics.id |
| auto_options | parent_id | auto_options.id |
| auto_option_values | equipment_id | auto_equipments.id |
| auto_option_values | option_id | auto_options.id |

Для каскадных списков основные индексы: (parent_fk, status, sort_order) на models, generations, series, modifications, equipments; у marks — (status, sort_order). У иерархических справочников — (parent_id, sort_order). FK и уникальные пары индексируются. Для независимого фильтра по option_id нужен его индекс, так же characteristic_id. Не создавать повторный индекс, если его уже покрывает существующий левый префикс.

## Пример цепочки Audi A6 C9

Локальные id условные; это иллюстрация связей, не готовый полный импорт.

| Таблица | Пример записи |
|---|---|
| auto_marks | id=1, name=Audi, name_ru=Ауди, url=audi |
| auto_models | id=1, mark_id=1, name=A6, url=a6 |
| auto_generations | id=1, model_id=1, name=VI (C9), url=vi-c9, year_from=2025, year_to=NULL |
| auto_series | id=1, generation_id=1, name=Седан, url=sedan |
| auto_modifications | id=1, series_id=1, name=3.0 AMT 367 л.с., полный привод, engine_volume=2995, engine_power=367, engine=petrol, transmission=robot, drive=awd |
| auto_equipments | id=1, modification_id=1, name=Base |
| auto_characteristics | id=1, code=dimensions, name=Размеры, parent_id=NULL, unit=NULL |
| auto_characteristics | id=2, code=length, name=Длина, parent_id=1, unit=мм |
| auto_characteristic_values | id=1, equipment_id=1, characteristic_id=2, value=4999 |

Пример значений опций должен заполняться по подтверждённому оснащению конкретной комплектации; наличие ABS у другой комплектации не переносится автоматически.

## Правила работы приложения

1. При изменении марки очищать все нижние поля; аналогично для модели, поколения, серии и модификации.
2. Проверять связи на сервере, даже если каскадные списки в интерфейсе уже ограничены.
3. Выключенный родитель скрывает всю ветку в публичном выборе; проверять status по всей цепочке.
4. В объявлении сохранять выбранный equipment_id, если комплектация установлена. Для неизвестной комплектации разрешить выбор modification_id отдельно; не создавать фиктивную комплектацию.
5. При размещении каталога в отдельной физической базе межбазовую ссылку объявления контролирует приложение: не рассчитывать на обычный FK через разные подключения.
6. Индивидуальные опции и изменения конкретного автомобиля сохраняются в предложении, а не меняют заводской каталог.
7. При импорте не объединять технические версии только по name; обработка внешних ID и журнал импорта — отдельная задача.
8. Проверять year_to >= year_from, когда оба известны. Для parent_id использовать RESTRICT, предотвращать циклы на уровне приложения.

## Граница версии

В этой версии ровно десять таблиц. Нет auto_configurations, auto_body_types и отдельных таблиц групп. Группы встроены в auto_characteristics и auto_options через parent_id.

Модель рынков, редакций оснащения, несколько методик измерения расхода, цветные изображения и история цен в эту версию не входят. Если появятся эти требования, схему следует расширить до импорта соответствующих данных.

Документ — спецификация. Миграции, модели Laravel и реальная база здесь не создавались.
