import {
    useCallback,
    useRef,
    useState,
    type ChangeEvent,
    type DragEvent,
    type InputHTMLAttributes,
    type Ref,
} from 'react';

export type FileMetadata = {
    name: string;
    size: number;
    type: string;
    url: string;
    id: string;
};

export type FileWithPreview = {
    file: File | FileMetadata;
    id: string;
    preview?: string;
};

export type FileUploadOptions = {
    maxFiles?: number;
    maxSize?: number;
    accept?: string;
    multiple?: boolean;
    initialFiles?: FileMetadata[];
    onFilesChange?: (files: FileWithPreview[]) => void;
    onFilesAdded?: (addedFiles: FileWithPreview[]) => void;
    onError?: (errors: string[]) => void;
};

export type FileUploadState = {
    files: FileWithPreview[];
    isDragging: boolean;
    errors: string[];
};

export type FileUploadActions = {
    addFiles: (files: FileList | File[]) => void;
    removeFile: (id: string) => void;
    clearFiles: () => void;
    clearErrors: () => void;
    reorderFiles: (orderedIds: string[]) => void;
    handleDragEnter: (e: DragEvent<HTMLElement>) => void;
    handleDragLeave: (e: DragEvent<HTMLElement>) => void;
    handleDragOver: (e: DragEvent<HTMLElement>) => void;
    handleDrop: (e: DragEvent<HTMLElement>) => void;
    handleFileChange: (e: ChangeEvent<HTMLInputElement>) => void;
    openFileDialog: () => void;
    getInputProps: (
        props?: InputHTMLAttributes<HTMLInputElement>,
    ) => InputHTMLAttributes<HTMLInputElement> & {
        ref: Ref<HTMLInputElement>;
    };
};

export const useFileUpload = (
    options: FileUploadOptions = {},
): [FileUploadState, FileUploadActions] => {
    const {
        maxFiles = Number.POSITIVE_INFINITY,
        maxSize = Number.POSITIVE_INFINITY,
        accept = '*',
        multiple = false,
        initialFiles = [],
        onFilesChange,
        onFilesAdded,
        onError,
    } = options;

    const [state, setState] = useState<FileUploadState>({
        files: initialFiles.map((file) => ({
            file,
            id: file.id,
            preview: file.url,
        })),
        isDragging: false,
        errors: [],
    });

    const inputRef = useRef<HTMLInputElement>(null);

    const validateFile = useCallback(
        (file: File | FileMetadata): string | null => {
            if (file.size > maxSize) {
                return `Файл «${file.name}» больше ${formatBytes(maxSize)}.`;
            }

            if (accept !== '*') {
                const acceptedTypes = accept
                    .split(',')
                    .map((type) => type.trim());
                const fileType =
                    file instanceof File ? file.type || '' : file.type;
                const fileExtension = `.${file.name.split('.').pop()}`;

                const isAccepted = acceptedTypes.some((type) => {
                    if (type.startsWith('.')) {
                        return (
                            fileExtension.toLowerCase() === type.toLowerCase()
                        );
                    }
                    if (type.endsWith('/*')) {
                        const baseType = type.split('/')[0];
                        return fileType.startsWith(`${baseType}/`);
                    }
                    return fileType === type;
                });

                if (!isAccepted) {
                    return `Файл «${file.name}» не подходит. Нужны JPEG, PNG или WebP.`;
                }
            }

            return null;
        },
        [accept, maxSize],
    );

    const createPreview = useCallback(
        (file: File | FileMetadata): string | undefined => {
            if (file instanceof File) {
                return URL.createObjectURL(file);
            }
            return file.url;
        },
        [],
    );

    const generateUniqueId = useCallback(
        (file: File | FileMetadata): string => {
            if (file instanceof File) {
                return `${file.name}-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
            }
            return file.id;
        },
        [],
    );

    const clearFiles = useCallback(() => {
        setState((prev) => {
            for (const file of prev.files) {
                if (
                    file.preview &&
                    file.file instanceof File &&
                    file.file.type.startsWith('image/')
                ) {
                    URL.revokeObjectURL(file.preview);
                }
            }

            if (inputRef.current) {
                inputRef.current.value = '';
            }

            const next = {
                ...prev,
                files: [],
                errors: [],
            };

            onFilesChange?.(next.files);
            return next;
        });
    }, [onFilesChange]);

    const addFiles = useCallback(
        (newFiles: FileList | File[]) => {
            if (!newFiles || newFiles.length === 0) {
                return;
            }

            const newFilesArray = Array.from(newFiles);
            const errors: string[] = [];

            setState((prev) => ({ ...prev, errors: [] }));

            if (!multiple) {
                clearFiles();
            }

            setState((prev) => {
                if (
                    multiple &&
                    maxFiles !== Number.POSITIVE_INFINITY &&
                    prev.files.length + newFilesArray.length > maxFiles
                ) {
                    const message = `Можно загрузить не больше ${maxFiles} файлов.`;
                    onError?.([message]);
                    return { ...prev, errors: [message] };
                }

                const validFiles: FileWithPreview[] = [];

                for (const file of newFilesArray) {
                    if (multiple) {
                        const isDuplicate = prev.files.some(
                            (existingFile) =>
                                existingFile.file.name === file.name &&
                                existingFile.file.size === file.size,
                        );

                        if (isDuplicate) {
                            continue;
                        }
                    }

                    if (file.size > maxSize) {
                        errors.push(
                            multiple
                                ? `Некоторые файлы больше ${formatBytes(maxSize)}.`
                                : `Файл больше ${formatBytes(maxSize)}.`,
                        );
                        continue;
                    }

                    const error = validateFile(file);
                    if (error) {
                        errors.push(error);
                    } else {
                        validFiles.push({
                            file,
                            id: generateUniqueId(file),
                            preview: createPreview(file),
                        });
                    }
                }

                if (validFiles.length === 0) {
                    if (errors.length > 0) {
                        onError?.(errors);
                        return { ...prev, errors };
                    }

                    return prev;
                }

                onFilesAdded?.(validFiles);

                const nextFiles = !multiple
                    ? validFiles
                    : [...prev.files, ...validFiles];
                onFilesChange?.(nextFiles);

                return {
                    ...prev,
                    files: nextFiles,
                    errors,
                };
            });

            if (inputRef.current) {
                inputRef.current.value = '';
            }
        },
        [
            maxFiles,
            multiple,
            maxSize,
            validateFile,
            createPreview,
            generateUniqueId,
            clearFiles,
            onFilesChange,
            onFilesAdded,
            onError,
        ],
    );

    const removeFile = useCallback(
        (id: string) => {
            setState((prev) => {
                const fileToRemove = prev.files.find((file) => file.id === id);
                if (
                    fileToRemove?.preview &&
                    fileToRemove.file instanceof File &&
                    fileToRemove.file.type.startsWith('image/')
                ) {
                    URL.revokeObjectURL(fileToRemove.preview);
                }

                const nextFiles = prev.files.filter((file) => file.id !== id);
                onFilesChange?.(nextFiles);

                return {
                    ...prev,
                    files: nextFiles,
                    errors: [],
                };
            });
        },
        [onFilesChange],
    );

    const reorderFiles = useCallback(
        (orderedIds: string[]) => {
            setState((prev) => {
                const byId = new Map(
                    prev.files.map((file) => [file.id, file] as const),
                );
                const nextFiles = orderedIds
                    .map((id) => byId.get(id))
                    .filter((file): file is FileWithPreview => file != null);

                for (const file of prev.files) {
                    if (!orderedIds.includes(file.id)) {
                        nextFiles.push(file);
                    }
                }

                onFilesChange?.(nextFiles);

                return { ...prev, files: nextFiles };
            });
        },
        [onFilesChange],
    );

    const clearErrors = useCallback(() => {
        setState((prev) => ({
            ...prev,
            errors: [],
        }));
    }, []);

    const handleDragEnter = useCallback((e: DragEvent<HTMLElement>) => {
        e.preventDefault();
        e.stopPropagation();
        setState((prev) => ({ ...prev, isDragging: true }));
    }, []);

    const handleDragLeave = useCallback((e: DragEvent<HTMLElement>) => {
        e.preventDefault();
        e.stopPropagation();

        if (e.currentTarget.contains(e.relatedTarget as Node)) {
            return;
        }

        setState((prev) => ({ ...prev, isDragging: false }));
    }, []);

    const handleDragOver = useCallback((e: DragEvent<HTMLElement>) => {
        e.preventDefault();
        e.stopPropagation();
    }, []);

    const handleDrop = useCallback(
        (e: DragEvent<HTMLElement>) => {
            e.preventDefault();
            e.stopPropagation();
            setState((prev) => ({ ...prev, isDragging: false }));

            if (inputRef.current?.disabled) {
                return;
            }

            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                if (!multiple) {
                    const file = e.dataTransfer.files[0];
                    if (file) {
                        addFiles([file]);
                    }
                } else {
                    addFiles(e.dataTransfer.files);
                }
            }
        },
        [addFiles, multiple],
    );

    const handleFileChange = useCallback(
        (e: ChangeEvent<HTMLInputElement>) => {
            if (e.target.files && e.target.files.length > 0) {
                addFiles(e.target.files);
            }
        },
        [addFiles],
    );

    const openFileDialog = useCallback(() => {
        inputRef.current?.click();
    }, []);

    const getInputProps = useCallback(
        (props: InputHTMLAttributes<HTMLInputElement> = {}) => ({
            ...props,
            type: 'file' as const,
            onChange: handleFileChange,
            accept: props.accept || accept,
            multiple: props.multiple !== undefined ? props.multiple : multiple,
            ref: inputRef,
        }),
        [accept, multiple, handleFileChange],
    );

    return [
        state,
        {
            addFiles,
            removeFile,
            clearFiles,
            clearErrors,
            reorderFiles,
            handleDragEnter,
            handleDragLeave,
            handleDragOver,
            handleDrop,
            handleFileChange,
            openFileDialog,
            getInputProps,
        },
    ];
};

export const formatBytes = (bytes: number, decimals = 1): string => {
    if (bytes === 0) {
        return '0 Б';
    }

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return `${Number.parseFloat((bytes / k ** i).toFixed(dm))} ${sizes[i] ?? 'Б'}`;
};
