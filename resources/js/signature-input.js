const initializeSignatureEditors = () => {
    document.querySelectorAll('[data-signature-editor]').forEach((editor) => {
        if (editor.dataset.initialized) {
            return;
        }
        editor.dataset.initialized = 'true';
        const canvas = editor.querySelector('[data-signature-canvas]');
        const context = canvas.getContext('2d');
        const result = editor.querySelector('[data-signature-result]');
        const status = editor.querySelector('[data-signature-status]');
        const error = editor.querySelector('[data-signature-error]');
        const fileInput = editor.querySelector('[data-signature-file]');
        let pointer = null;
        let previous = null;
        let hasInk = false;
        let dirty = false;
        let ready = false;
        let busy = false;
        let generation = 0;
        const wire = () => window.Livewire?.find(editor.closest('[wire\\:id]')?.getAttribute('wire:id'));
        const setState = (value) => {
            editor.querySelector('[data-signature-state]').value = value;
            wire()?.set(editor.dataset.statePath, value, false);
        };
        const message = (text = '') => {
            error.textContent = text;
            error.hidden = !text;
        };
        const invalidate = () => {
            generation++;
            ready = false;
            result.hidden = true;
            setState('');
            message();
        };
        const resize = () => {
            if (hasInk) {
                return;
            }
            const bounds = canvas.getBoundingClientRect();
            if (!bounds.width) {
                return;
            }
            const scale = Math.min(Math.max(2, window.devicePixelRatio || 1),
                4096 / bounds.width, 2048 / bounds.height,
                Math.sqrt(4194304 / (bounds.width * bounds.height)));
            canvas.width = Math.floor(bounds.width * scale);
            canvas.height = Math.floor(bounds.height * scale);
            context.strokeStyle = '#000000';
            context.lineWidth = 2 * scale;
            context.lineCap = 'round';
            context.lineJoin = 'round';
        };
        const clear = () => {
            invalidate();
            context.clearRect(0, 0, canvas.width, canvas.height);
            fileInput.value = '';
            hasInk = false;
            dirty = false;
            status.textContent = '';
            resize();
        };
        const point = (event) => {
            const bounds = canvas.getBoundingClientRect();
            return { x: (event.clientX - bounds.left) * canvas.width / bounds.width, y: (event.clientY - bounds.top) * canvas.height / bounds.height };
        };
        canvas.addEventListener('pointerdown', (event) => {
            if (busy || pointer !== null || (event.pointerType === 'mouse' && event.button !== 0)) {
                return;
            }
            event.preventDefault();
            invalidate();
            dirty = true;
            pointer = event.pointerId;
            canvas.setPointerCapture(pointer);
            previous = point(event);
            context.beginPath();
            context.moveTo(previous.x, previous.y);
        });
        canvas.addEventListener('pointermove', (event) => {
            if (pointer !== event.pointerId) {
                return;
            }
            event.preventDefault();
            const samples = event.getCoalescedEvents?.();
            for (const sample of samples?.length ? samples : [event]) {
                const next = point(sample);
                const middle = { x: (previous.x + next.x) / 2, y: (previous.y + next.y) / 2 };
                context.quadraticCurveTo(previous.x, previous.y, middle.x, middle.y);
                context.stroke();
                context.beginPath();
                context.moveTo(middle.x, middle.y);
                previous = next;
                hasInk = true;
            }
        });
        const finishStroke = (event) => {
            if (pointer !== event.pointerId) {
                return;
            }
            const last = point(event);
            context.lineTo(last.x, last.y);
            context.stroke();
            hasInk = true;
            pointer = null;
        };
        canvas.addEventListener('pointerup', finishStroke);
        canvas.addEventListener('pointercancel', () => { pointer = null; });
        canvas.addEventListener('lostpointercapture', () => { pointer = null; });
        const prepare = async (file, source) => {
            invalidate();
            const currentGeneration = generation;
            dirty = true;
            if (!file || file.size > 2 * 1024 * 1024) {
                message('Choose a PNG or JPEG no larger than 2 MB.');
                return;
            }
            busy = true;
            status.textContent = 'Preparing your signature preview…';
            const body = new FormData();
            body.append('image', file, file.name || 'drawing.png');
            body.append('source', source);
            try {
                const response = await fetch(editor.dataset.prepareUrl, {
                    method: 'POST', credentials: 'same-origin', body,
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' },
                });
                const payload = await response.json();
                if (currentGeneration !== generation) {
                    return;
                }
                if (!response.ok) {
                    throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'Unable to prepare signature.');
                }
                editor.querySelector('[data-signature-image]').src = payload.data_url;
                setState(JSON.stringify({ source, data_url: payload.data_url }));
                result.hidden = false;
                ready = true;
                status.textContent = 'Preview ready. Submit Update Request to send this version for KYC review.';
            } catch (exception) {
                if (currentGeneration === generation) {
                    message(exception.message || 'Unable to prepare signature. Please try again.');
                    status.textContent = '';
                }
            } finally {
                busy = false;
            }
        };
        editor.querySelectorAll('[data-signature-mode]').forEach((button) => button.addEventListener('click', () => {
            if (busy) {
                return;
            }
            clear();
            const mode = button.dataset.signatureMode;
            editor.querySelector('[data-signature-draw]').hidden = mode !== 'draw';
            editor.querySelector('[data-signature-upload]').hidden = mode !== 'upload';
            editor.querySelectorAll('[data-signature-mode]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
            resize();
        }));
        editor.querySelector('[data-signature-clear]').addEventListener('click', clear);
        editor.querySelector('[data-signature-again]').addEventListener('click', clear);
        editor.querySelector('[data-signature-preview]').addEventListener('click', () => {
            if (busy) {
                return;
            }
            if (!hasInk) {
                message('Draw a signature before preparing the preview.');
                return;
            }
            canvas.toBlob((blob) => prepare(blob, 'draw'), 'image/png');
        });
        fileInput.addEventListener('change', () => prepare(fileInput.files[0], 'upload'));
        editor.closest('form')?.addEventListener('submit', (event) => {
            if (busy || (dirty && !ready)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                message('Prepare and check the signature preview before submitting.');
            }
        }, true);
        new ResizeObserver(resize).observe(canvas);
        resize();
    });
};

document.addEventListener('DOMContentLoaded', initializeSignatureEditors);
document.addEventListener('livewire:navigated', initializeSignatureEditors);
if (document.readyState !== 'loading') {
    initializeSignatureEditors();
}
