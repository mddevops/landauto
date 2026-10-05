import { Badge } from '@/components/ui/badge';

export type DeliveryStatus =
    | 'pending'
    | 'processing'
    | 'delivered'
    | 'retry_scheduled'
    | 'failed'
    | 'cancelled';

export function DeliveryStatusBadge({
    status,
    label,
}: {
    status: DeliveryStatus;
    label: string;
}) {
    const variant =
        status === 'delivered'
            ? 'secondary'
            : status === 'failed'
              ? 'destructive'
              : 'outline';

    return <Badge variant={variant}>{label}</Badge>;
}
