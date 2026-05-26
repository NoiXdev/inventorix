<div
    x-data="qrPrintModal"
    x-init="init()"
    x-show="isOpen"
    x-cloak
    @keydown.escape.window="close()"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
>
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-3xl w-full mx-4 p-6 space-y-4">
        <div class="flex items-start justify-between">
            <h2 class="text-lg font-semibold">QR-Code drucken</h2>
            <button type="button" @click="close()" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>

        <template x-if="!webUsbSupported">
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded p-3 text-sm">
                Web USB Druck wird nur in Chrome/Edge unterstützt. Bitte einen Chromium-Browser verwenden.
            </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-3">
                <label class="block text-sm">
                    <span class="font-medium">Etikettenrolle</span>
                    <select x-model="rollId" @change="renderPreview()"
                        class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                        <template x-for="roll in allRolls()" :key="roll.id">
                            <option :value="roll.id" x-text="roll.label"></option>
                        </template>
                    </select>
                </label>

                <fieldset class="space-y-1">
                    <legend class="text-sm font-medium">Layout</legend>
                    <template x-for="opt in allLayouts()" :key="opt.value">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="qr-layout" :value="opt.value"
                                x-model="layout" :disabled="opt.disabled"
                                @change="renderPreview()">
                            <span :class="opt.disabled ? 'text-gray-400' : ''" x-text="opt.label"></span>
                        </label>
                    </template>
                </fieldset>

                <div class="border-t pt-3 text-sm">
                    <template x-if="!paired">
                        <button type="button" @click="pair()" :disabled="pairing || !webUsbSupported"
                            class="px-3 py-1.5 rounded bg-amber-500 text-white disabled:opacity-50">
                            <span x-text="pairing ? 'Verbinde…' : 'Drucker verbinden'"></span>
                        </button>
                    </template>
                    <template x-if="paired">
                        <div class="flex items-center gap-2">
                            <span class="text-green-700">Verbunden: Brother QL-800</span>
                        </div>
                    </template>
                </div>
            </div>

            <div>
                <div class="text-sm font-medium mb-1">Vorschau (erstes Etikett)</div>
                <div id="qr-print-preview" class="flex items-center justify-center bg-gray-50 dark:bg-gray-800 rounded p-2 min-h-[320px]"></div>
            </div>
        </div>

        <template x-if="errorMessage">
            <div class="bg-red-50 border border-red-200 text-red-800 rounded p-3 text-sm" x-text="errorMessage"></div>
        </template>

        <template x-if="statusMessage">
            <div class="bg-green-50 border border-green-200 text-green-800 rounded p-3 text-sm" x-text="statusMessage"></div>
        </template>

        <div class="flex items-center justify-between pt-3 border-t">
            <div class="text-sm text-gray-600">
                <span x-text="total"></span> Etiketten
                <template x-if="printing">
                    <span> &middot; <span x-text="completed"></span> / <span x-text="total"></span> gedruckt</span>
                </template>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="close()" :disabled="printing"
                    class="px-3 py-1.5 rounded border">Abbrechen</button>
                <template x-if="!printing">
                    <button type="button" @click="print()" :disabled="!paired || !webUsbSupported"
                        class="px-3 py-1.5 rounded bg-amber-500 text-white disabled:opacity-50">Drucken</button>
                </template>
                <template x-if="printing">
                    <button type="button" @click="cancel()"
                        class="px-3 py-1.5 rounded bg-red-500 text-white">Stoppen</button>
                </template>
            </div>
        </div>
    </div>
</div>
