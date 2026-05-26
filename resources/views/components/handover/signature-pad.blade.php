@props([
    'statePath' => null,
    'getStatePath' => null,
    'width' => 600,
    'height' => 200,
    'clearLabel' => __('handover.sign.clear'),
])

<div
    x-data="{
        drawing: false,
        ctx: null,
        canvas: null,
        last: null,
        stroked: false,
        init() {
            this.canvas = this.$refs.canvas;
            this.ctx = this.canvas.getContext('2d');
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.lineWidth = 2;
            this.ctx.strokeStyle = '#111';

            const fill = () => {
                this.ctx.fillStyle = '#fff';
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                this.persist();
            };
            fill();
        },
        pointerDown(ev) {
            this.drawing = true;
            this.stroked = true;
            const r = this.canvas.getBoundingClientRect();
            this.last = { x: ev.clientX - r.left, y: ev.clientY - r.top };
        },
        pointerMove(ev) {
            if (!this.drawing) return;
            const r = this.canvas.getBoundingClientRect();
            const next = { x: ev.clientX - r.left, y: ev.clientY - r.top };
            this.ctx.beginPath();
            this.ctx.moveTo(this.last.x, this.last.y);
            this.ctx.lineTo(next.x, next.y);
            this.ctx.stroke();
            this.last = next;
        },
        pointerUp() {
            if (!this.drawing) return;
            this.drawing = false;
            this.persist();
        },
        clear() {
            this.ctx.fillStyle = '#fff';
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
            this.stroked = false;
            this.persist();
        },
        persist() {
            const dataUrl = this.canvas.toDataURL('image/png');
            const base64 = dataUrl.replace(/^data:image\/png;base64,/, '');
            $wire.set(@js($getStatePath ? $getStatePath() : $statePath), this.stroked ? base64 : '');
        },
    }"
    class="space-y-2"
>
    <div class="border border-gray-300 rounded inline-block bg-white">
        <canvas
            x-ref="canvas"
            width="{{ $width }}"
            height="{{ $height }}"
            style="touch-action: none; cursor: crosshair; display: block;"
            @pointerdown="pointerDown($event)"
            @pointermove="pointerMove($event)"
            @pointerup="pointerUp()"
            @pointerleave="pointerUp()"
        ></canvas>
    </div>
    <button
        type="button"
        class="text-sm px-3 py-1 border rounded text-gray-700 hover:bg-gray-100"
        @click="clear()"
    >
        {{ $clearLabel }}
    </button>
</div>
