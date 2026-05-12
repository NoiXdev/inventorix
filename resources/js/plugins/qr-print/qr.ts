import QRCode from 'qrcode';

export async function generateQrMatrix(data: string): Promise<boolean[][]> {
    const qr = QRCode.create(data, { errorCorrectionLevel: 'M' });
    const size = qr.modules.size;
    const matrix: boolean[][] = [];
    for (let y = 0; y < size; y++) {
        const row: boolean[] = [];
        for (let x = 0; x < size; x++) {
            row.push(qr.modules.get(x, y) === 1);
        }
        matrix.push(row);
    }
    return matrix;
}
