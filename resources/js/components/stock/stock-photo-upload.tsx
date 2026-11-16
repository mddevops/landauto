import { CloudUpload, GripVertical, ImagePlus, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { IMAGE_ACCEPT } from '@/components/catalog/photo-parts';
import {
    Sortable,
    SortableItem,
    SortableItemHandle,
} from '@/components/reui/sortable';
import { Button } from '@/components/ui/button';
import {
    formatBytes,
    useFileUpload,
    type FileWithPreview,
} from '@/hooks/use-file-upload';
import { cn } from '@/lib/utils';

/** D-061 / ImageUpload::MAX_KILOBYTES — keep client limit aligned with backend. */
export const STOCK_PHOTO_MAX_BYTES = 10 * 1024 * 1024;

export const STOCK_PHOTO_MAX_FILES_WIZARD = 40;

export const STOCK_PHOTO_MAX_FILES_SHOW = 20;

type Props = {
    onChange: (files: File[]) => void;
    maxFiles?: number;
    className?: string;
    /** When true, clear the picker after a successful upload (show page). */
    resetKey?: number;
};

function asFileList(items: FileWithPreview[]): File[] {
    return items
        .map((item) => item.file)
        .filter((file): file is File => file instanceof File);
}

/**
 * Multi-image dropzone with drag-reorder (ReUI Sortable + useFileUpload).
 * First tile is the cover order for stock gallery.
 */
export function StockPhotoUpload({
    onChange,
    maxFiles = STOCK_PHOTO_MAX_FILES_WIZARD,
    className,
    resetKey = 0,
}: Props) {
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    const [
        { files, isDragging, errors },
        {
            removeFile,
            clearFiles,
            reorderFiles,
            openFileDialog,
            getInputProps,
            handleDragEnter,
            handleDragLeave,
            handleDragOver,
            handleDrop,
        },
    ] = useFileUpload({
        accept: IMAGE_ACCEPT,
        multiple: true,
        maxFiles,
        maxSize: STOCK_PHOTO_MAX_BYTES,
        onFilesChange: (next) => onChangeRef.current(asFileList(next)),
    });

    const clearFilesRef = useRef(clearFiles);
    clearFilesRef.current = clearFiles;

    useEffect(() => {
        if (resetKey > 0) {
            clearFilesRef.current();
        }
    }, [resetKey]);

    return (
        <div className={cn('space-y-3', className)}>
            <p className="text-sm text-muted-foreground">
                До {maxFiles} фото · JPEG, PNG, WebP · до{' '}
                {formatBytes(STOCK_PHOTO_MAX_BYTES)} каждое. Первое фото —
                обложка; перетащите, чтобы изменить порядок.
            </p>

            {files.length > 0 && (
                <Sortable
                    value={files}
                    onValueChange={(next) =>
                        reorderFiles(next.map((item) => item.id))
                    }
                    getItemValue={(item) => item.id}
                    strategy="grid"
                    className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4"
                >
                    {files.map((item, index) => (
                        <SortableItem
                            key={item.id}
                            value={item.id}
                            className="group/item relative overflow-hidden rounded-md border bg-muted/30"
                        >
                            <img
                                src={item.preview}
                                alt={
                                    item.file instanceof File
                                        ? item.file.name
                                        : item.file.name
                                }
                                className="pointer-events-none aspect-[4/3] w-full object-cover"
                            />
                            {index === 0 && (
                                <span className="absolute top-2 left-2 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-medium text-white">
                                    Обложка
                                </span>
                            )}
                            <SortableItemHandle className="absolute bottom-2 left-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="icon"
                                    className="size-7 opacity-0 shadow-sm transition-opacity group-focus-within/item:opacity-100 group-hover/item:opacity-100"
                                    aria-label="Изменить порядок"
                                >
                                    <GripVertical className="size-3.5" />
                                </Button>
                            </SortableItemHandle>
                            <Button
                                type="button"
                                variant="secondary"
                                size="icon"
                                className="absolute top-2 right-2 size-7 opacity-0 shadow-sm transition-opacity group-focus-within/item:opacity-100 group-hover/item:opacity-100"
                                onClick={() => removeFile(item.id)}
                                aria-label="Убрать фото"
                            >
                                <X className="size-3.5" />
                            </Button>
                        </SortableItem>
                    ))}
                </Sortable>
            )}

            <div
                role="button"
                tabIndex={0}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openFileDialog();
                    }
                }}
                onClick={openFileDialog}
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
                className={cn(
                    'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-8 text-center transition-colors',
                    isDragging
                        ? 'border-primary bg-primary/5'
                        : 'border-muted-foreground/25 hover:border-muted-foreground/50',
                )}
            >
                <div className="flex size-10 items-center justify-center rounded-full bg-muted">
                    {files.length === 0 ? (
                        <ImagePlus className="size-4 text-muted-foreground" />
                    ) : (
                        <CloudUpload className="size-4 text-muted-foreground" />
                    )}
                </div>
                <div className="space-y-1">
                    <p className="text-sm font-medium">
                        Перетащите фото сюда или выберите файлы
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {files.length > 0
                            ? `Выбрано ${files.length} из ${maxFiles}`
                            : `JPEG, PNG, WebP · до ${formatBytes(STOCK_PHOTO_MAX_BYTES)}`}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={(event) => {
                        event.stopPropagation();
                        openFileDialog();
                    }}
                >
                    Выбрать файлы
                </Button>
                <input {...getInputProps()} className="sr-only" />
            </div>

            {errors.length > 0 && (
                <ul className="space-y-1 text-sm text-destructive" role="alert">
                    {errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

type SavedPhoto = {
    public_id: string;
    url: string;
    original_name: string;
};

/**
 * Sortable grid for already saved stock photos (cover = first).
 */
export function StockSavedPhotoGrid({
    photos,
    onReorder,
    onRemove,
    coverBadge,
}: {
    photos: SavedPhoto[];
    onReorder: (orderedIds: string[]) => void;
    onRemove: (publicId: string) => void;
    coverBadge?: string | null;
}) {
    if (photos.length === 0) {
        return null;
    }

    return (
        <Sortable
            value={photos}
            onValueChange={(next) =>
                onReorder(next.map((photo) => photo.public_id))
            }
            getItemValue={(photo) => photo.public_id}
            strategy="grid"
            className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4"
        >
            {photos.map((photo, index) => (
                <SortableItem
                    key={photo.public_id}
                    value={photo.public_id}
                    className="group/item relative overflow-hidden rounded-md border"
                >
                    <img
                        src={photo.url}
                        alt={photo.original_name}
                        className="pointer-events-none aspect-[4/3] w-full object-cover"
                    />
                    {index === 0 && (
                        <span className="absolute top-2 left-2 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-medium text-white">
                            {coverBadge ?? 'Обложка'}
                        </span>
                    )}
                    <SortableItemHandle className="absolute bottom-2 left-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="icon"
                            className="size-7 opacity-0 shadow-sm transition-opacity group-focus-within/item:opacity-100 group-hover/item:opacity-100"
                            aria-label="Изменить порядок"
                        >
                            <GripVertical className="size-3.5" />
                        </Button>
                    </SortableItemHandle>
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon"
                        className="absolute top-2 right-2 size-7 opacity-0 shadow-sm transition-opacity group-focus-within/item:opacity-100 group-hover/item:opacity-100"
                        onClick={() => onRemove(photo.public_id)}
                        aria-label="Удалить фото"
                    >
                        <X className="size-3.5" />
                    </Button>
                </SortableItem>
            ))}
        </Sortable>
    );
}
