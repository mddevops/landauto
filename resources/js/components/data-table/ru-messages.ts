import {
    enTableMessages,
    mergeTableMessages,
    type TableMessages,
} from '@querycn/table-react';

export const ruTableMessages: TableMessages = mergeTableMessages(
    enTableMessages,
    {
        columns: {
            menu: 'Колонки',
            hide: 'Скрыть',
            pinStart: 'Закрепить слева',
            pinEnd: 'Закрепить справа',
            unpin: 'Открепить',
            color: 'Цвет',
            noColor: 'Без цвета',
            customColor: 'Свой цвет',
            fitContent: 'По содержимому',
            fitAll: 'Подогнать все',
            resetLayout: 'Сбросить вид',
            options: (column) => `Настройки колонки «${column}»`,
        },
        colors: {
            red: 'Красный',
            orange: 'Оранжевый',
            amber: 'Янтарный',
            green: 'Зелёный',
            teal: 'Бирюзовый',
            blue: 'Синий',
            violet: 'Фиолетовый',
            pink: 'Розовый',
        },
        sorting: {
            asc: 'По возрастанию',
            desc: 'По убыванию',
            clear: 'Сбросить сортировку',
        },
        pagination: {
            label: 'Страницы',
            rowsPerPage: 'Строк на странице',
            first: 'Первая страница',
            previous: 'Предыдущая страница',
            next: 'Следующая страница',
            last: 'Последняя страница',
            morePages: 'Ещё страницы',
        },
        selection: {
            selectAll: 'Выбрать все строки',
            selectRow: 'Выбрать строку',
            clear: 'Снять выбор',
        },
        search: {
            label: 'Поиск',
            placeholder: 'Поиск',
            clear: 'Очистить поиск',
        },
        actions: {
            reload: 'Обновить',
            clearFilters: 'Сбросить фильтры',
            retry: 'Повторить',
        },
        states: {
            empty: 'Пользователей пока нет.',
            error: 'Не удалось показать таблицу.',
            loading: 'Загрузка',
        },
        editing: {
            saving: 'Сохранение',
            saveFailed: 'Не удалось сохранить',
            invalidNumber: 'Нужно число',
            search: 'Поиск',
            noOptions: 'Ничего не найдено',
            loadFailed: 'Не удалось загрузить варианты',
            edit: (column) => `Изменить «${column}»`,
        },
        bulkEdit: {
            open: 'Изменить выбранные',
            title: 'Изменение выбранных строк',
            column: 'Колонка',
            chooseColumn: 'Выберите колонку',
            value: 'Значение',
            chooseValue: 'Выберите значение',
            addField: 'Добавить поле',
            cancel: 'Отмена',
            applying: 'Сохранение',
            description: (count) => `Изменение затронет строк: ${count}.`,
            removeField: (column) => `Убрать «${column}»`,
            apply: (count) => `Применить к ${count}`,
        },
        header: {
            move: (column) => `Переместить колонку «${column}»`,
            resize: (column) => `Изменить ширину колонки «${column}»`,
            instructions:
                'Пробел — взять колонку, стрелки — переместить, пробел — отпустить, Escape — отмена.',
            pickedUp: (column) => `Колонка «${column}» взята`,
            movedTo: (column, position, total) =>
                `Колонка «${column}» на позиции ${position} из ${total}`,
            dropped: (column, position, total) =>
                `Колонка «${column}» поставлена на позицию ${position} из ${total}`,
            cancelled: (column) => `Перемещение колонки «${column}» отменено`,
        },
        counts: {
            number: (value) => new Intl.NumberFormat('ru-RU').format(value),
            page: (page, pageCount) =>
                pageCount === undefined
                    ? `Страница ${page}`
                    : `Страница ${page} из ${pageCount}`,
            selected: (selected, total) => `Выбрано ${selected} из ${total}`,
            rows: (count) => `Строк: ${count}`,
        },
    },
);
