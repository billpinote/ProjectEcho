<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div data-signature-editor data-state-path="{{ $getStatePath() }}" data-prepare-url="{{ route('pilot-signatures.prepare') }}" wire:ignore class="space-y-4">
        <div class="flex flex-wrap gap-3" role="group" aria-label="Signature input method">
            <x-filament::button type="button" color="gray" data-signature-mode="draw" aria-pressed="true">Draw Signature</x-filament::button>
            <x-filament::button type="button" color="gray" data-signature-mode="upload" aria-pressed="false">Upload Signature</x-filament::button>
        </div>
        <div data-signature-draw class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">Draw with a mouse, finger, or stylus. Black strokes are captured on a transparent background.</p>
            <canvas data-signature-canvas class="echo-signature-canvas" aria-label="Draw your signature" role="img"></canvas>
            <div class="flex flex-wrap gap-3">
                <x-filament::button type="button" color="gray" size="sm" data-signature-clear>Clear</x-filament::button>
                <x-filament::button type="button" color="gray" size="sm" data-signature-preview>Preview Signature</x-filament::button>
            </div>
        </div>
        <div data-signature-upload hidden class="space-y-3">
            <label class="block text-sm font-medium" for="{{ $getId() }}-upload">Upload a PNG or JPEG (up to 2 MB)</label>
            <input id="{{ $getId() }}-upload" data-signature-file type="file" accept="image/png,image/jpeg" class="block w-full text-sm" />
            <p class="text-sm text-gray-600 dark:text-gray-400">32 × 16 to 4096 × 2048 pixels, up to 4 megapixels. Blank margins and near-white background are removed before review. Your preview shows the cleaned image.</p>
        </div>
        <p data-signature-error role="alert" hidden class="text-sm text-danger-600"></p>
        <p data-signature-status role="status" class="text-sm text-gray-600 dark:text-gray-400"></p>
        <div data-signature-result hidden class="space-y-3">
            <p class="text-sm font-medium">PDF signature-box preview (322 × 28 pt)</p>
            <div class="echo-signature-pdf-preview"><img data-signature-image alt="Proposed signature at PDF box size" /></div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Scales to fit on small screens. Check the appearance before submitting this profile update. Pending review—not available for signing.</p>
            <x-filament::button type="button" color="gray" size="sm" data-signature-again>Draw Again / Choose Another</x-filament::button>
        </div>
        <input data-signature-state type="hidden" wire:model="{{ $getStatePath() }}" />
    </div>
</x-dynamic-component>
