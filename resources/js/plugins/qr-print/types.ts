export type Uuid = string;

export interface LabelItem {
    uuid: Uuid;
    metadata?: {
        modelName: string;
        serial: string;
    };
}

export type LayoutKind = 'qr-only' | 'qr-uuid' | 'qr-asset';

export interface RollSpec {
    id: string;
    label: string;
    mediaTypeByte: number;
    widthMm: number;
    lengthMm: number | null;
    printWidthPx: number;
    printHeightPx: number;
    printableStartByte: number;
    printableEndByte: number;
}

export interface PrintJob {
    items: LabelItem[];
    roll: RollSpec;
    layout: LayoutKind;
}

export interface PrintProgress {
    completedLabels: number;
    totalLabels: number;
}

export interface OpenEventDetail {
    items: LabelItem[];
    defaults?: {
        layout?: LayoutKind;
        rollId?: string;
    };
}
