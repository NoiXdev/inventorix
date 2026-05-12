import { composeLabel } from './layout';
import { rasterize } from './rasterizer';
import { buildJob, buildCancelReset } from './brother-protocol';
import { WebUsbTransport } from './webusb-transport';
import type { LabelItem, PrintJob, PrintProgress, RollSpec, LayoutKind } from './types';

type ProgressCallback = (progress: PrintProgress) => void;

export class PrintController {
    private transport = new WebUsbTransport();
    private cancelled = false;

    async pair(): Promise<void> {
        await this.transport.pair();
        await this.transport.open();
    }

    async tryReconnect(): Promise<boolean> {
        const found = await this.transport.findPaired();
        if (found) {
            await this.transport.open();
            return true;
        }
        return false;
    }

    async print(job: PrintJob, onProgress?: ProgressCallback): Promise<void> {
        this.cancelled = false;

        const labels: Uint8Array[][] = [];
        for (const item of job.items) {
            const image = await composeLabel(item, job.roll, job.layout);
            labels.push(rasterize(image, job.roll));
        }

        const bytes = buildJob(labels, job.roll);

        // Build a map: byte offset -> label index (for progress reporting).
        const labelByteOffsets = this.computeLabelOffsets(labels, job.roll);

        await this.transport.send(bytes, (writtenBytes) => {
            if (this.cancelled) return;
            const completed = labelByteOffsets.findIndex(offset => writtenBytes < offset);
            const completedLabels = completed === -1 ? labels.length : completed;
            onProgress?.({ completedLabels, totalLabels: labels.length });
        });

        onProgress?.({ completedLabels: labels.length, totalLabels: labels.length });
    }

    async cancel(): Promise<void> {
        this.cancelled = true;
        await this.transport.send(buildCancelReset());
    }

    async close(): Promise<void> {
        await this.transport.close();
    }

    private computeLabelOffsets(labels: Uint8Array[][], _roll: RollSpec): number[] {
        // Rebuild segment lengths to compute cumulative byte offsets at each label boundary.
        // Each label contributes: 13 (print-info) + 4 (various) + 4 (advanced) + 5 (margin) + 2 (compression off)
        // + 1 (terminator) + rasterLines * (3 + 90) = 29 + rasterLines * 93.
        // Plus the per-job header: 200 + 2 + 4 + 4 = 210 bytes before the first label.
        const HEADER = 200 + 2 + 4 + 4;
        const PER_LABEL_OVERHEAD = 13 + 4 + 4 + 5 + 2 + 1;
        const PER_LINE = 3 + 90;

        const offsets: number[] = [];
        let cursor = HEADER;
        for (const lines of labels) {
            cursor += PER_LABEL_OVERHEAD + lines.length * PER_LINE;
            offsets.push(cursor);
        }
        return offsets;
    }
}

export type { LabelItem, LayoutKind };
