/** auto.ru-style body colour swatches for the Stock wizard. */
export type StockColor = {
    id: string;
    name: string;
    hex: string;
    gradient: string | null;
};

export const STOCK_COLORS: StockColor[] = [
    {
        id: '040001',
        name: 'Чёрный',
        hex: '#000000',
        gradient: null,
    },
    {
        id: 'CACECB',
        name: 'Серебристый',
        hex: '#C1C1C1',
        gradient: 'linear-gradient(#F0F0F0, #C1C1C1)',
    },
    {
        id: 'FAFBFB',
        name: 'Белый',
        hex: '#FFFFFF',
        gradient: null,
    },
    {
        id: '97948F',
        name: 'Серый',
        hex: '#9C9999',
        gradient: null,
    },
    {
        id: '0000CC',
        name: 'Синий',
        hex: '#334DFF',
        gradient: null,
    },
    {
        id: 'EE1D19',
        name: 'Красный',
        hex: '#FC4829',
        gradient: null,
    },
    {
        id: '007F00',
        name: 'Зелёный',
        hex: '#35BA2B',
        gradient: null,
    },
    {
        id: '200204',
        name: 'Коричневый',
        hex: '#926547',
        gradient: null,
    },
    {
        id: 'C49648',
        name: 'Бежевый',
        hex: '#F1D9B2',
        gradient: null,
    },
    {
        id: '22A0F8',
        name: 'Голубой',
        hex: '#36A1FF',
        gradient: null,
    },
    {
        id: 'DEA522',
        name: 'Золотистый',
        hex: '#FABE00',
        gradient: 'linear-gradient(#FFED02, #FD9C00)',
    },
    {
        id: '660099',
        name: 'Бордовый',
        hex: '#C5172D',
        gradient: null,
    },
    {
        id: '4A2197',
        name: 'Фиолетовый',
        hex: '#9966CC',
        gradient: null,
    },
    {
        id: 'FFD600',
        name: 'Жёлтый',
        hex: '#FDE90F',
        gradient: null,
    },
    {
        id: 'FF8649',
        name: 'Оранжевый',
        hex: '#FF9966',
        gradient: null,
    },
    {
        id: 'FFC0CB',
        name: 'Розовый',
        hex: '#FFC0CB',
        gradient: null,
    },
];
