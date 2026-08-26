const readExifOrientation = async (file) => {
    if (!/^image\/jpe?g$/i.test(file.type)) {
        return 1;
    }

    const bytes = new DataView(await file.arrayBuffer());
    if (bytes.getUint16(0, false) !== 0xffd8) {
        return 1;
    }

    let offset = 2;
    while (offset + 4 <= bytes.byteLength) {
        const marker = bytes.getUint16(offset, false);
        offset += 2;
        if (marker === 0xffda || marker === 0xffd9) break;
        const length = bytes.getUint16(offset, false);
        if (marker === 0xffe1 && bytes.getUint32(offset + 2, false) === 0x45786966) {
            const tiff = offset + 8;
            const little = bytes.getUint16(tiff, false) === 0x4949;
            const get16 = (at) => bytes.getUint16(at, little);
            const get32 = (at) => bytes.getUint32(at, little);
            const ifd = tiff + get32(tiff + 4);
            for (let i = 0; i < get16(ifd); i++) {
                const entry = ifd + 2 + i * 12;
                if (get16(entry) === 0x0112) return get16(entry + 8);
            }
            return 1;
        }
        offset += length;
    }
    return 1;
};

const drawOriented = (context, source, width, height, orientation) => {
    const swaps = orientation >= 5 && orientation <= 8;
    const outputWidth = swaps ? height : width;
    const outputHeight = swaps ? width : height;
    context.canvas.width = outputWidth;
    context.canvas.height = outputHeight;
    const transforms = {
        2: [-1, 0, 0, 1, width, 0],
        3: [-1, 0, 0, -1, width, height],
        4: [1, 0, 0, -1, 0, height],
        5: [0, 1, 1, 0, 0, 0],
        6: [0, 1, -1, 0, height, 0],
        7: [0, -1, -1, 0, height, width],
        8: [0, -1, 1, 0, 0, width],
    };
    context.setTransform(...(transforms[orientation] || [1, 0, 0, 1, 0, 0]));
    context.drawImage(source, 0, 0, width, height);
    context.setTransform(1, 0, 0, 1, 0, 0);
    return { width: outputWidth, height: outputHeight };
};

const normalizeQrImage = async (file) => {
    let source;
    let width;
    let height;
    let closeSource = false;

    try {
        if (window.createImageBitmap) {
            source = await window.createImageBitmap(file, { imageOrientation: 'from-image' });
            closeSource = true;
        }
    } catch (_) {
        source = null;
    }

    if (!source) {
        source = await new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = reject;
            image.src = URL.createObjectURL(file);
        });
        width = source.naturalWidth;
        height = source.naturalHeight;
    } else {
        width = source.width;
        height = source.height;
    }

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) throw new Error('Unable to create QR normalization canvas');
    const orientation = closeSource ? 1 : await readExifOrientation(file);
    const dimensions = drawOriented(context, source, width, height, orientation);
    if (closeSource) source.close();

    return { canvas, width: dimensions.width, height: dimensions.height };
};

const canvasFile = (canvas, name) => new Promise((resolve, reject) => {
    canvas.toBlob((blob) => blob
        ? resolve(new File([blob], name, { type: 'image/png' }))
        : reject(new Error('Unable to serialize normalized QR image')), 'image/png');
});

window.EchoQrImageDecoder = async (file, scannerRegionId, Html5Qrcode) => {
    console.info('[Echo QR] upload received', {
        name: file.name, type: file.type || 'unknown', size: file.size,
    });
    const normalized = await normalizeQrImage(file);
    console.info('[Echo QR] normalized image', {
        width: normalized.width, height: normalized.height,
        decoder: 'html5-qrcode scanFile -> ZXing',
    });

    const variants = [normalized.canvas];
    const grayscale = document.createElement('canvas');
    grayscale.width = normalized.width;
    grayscale.height = normalized.height;
    const grayContext = grayscale.getContext('2d', { willReadFrequently: true });
    grayContext.filter = 'grayscale(1) contrast(1.15)';
    grayContext.drawImage(normalized.canvas, 0, 0);
    variants.push(grayscale);

    let lastError;
    for (let index = 0; index < variants.length; index++) {
        const reader = new Html5Qrcode(scannerRegionId, { verbose: true });
        try {
            console.info('[Echo QR] decode attempt', index + 1, 'of', variants.length);
            const decoded = await reader.scanFile(
                await canvasFile(variants[index], `echo-qr-${index}.png`), false);
            console.info('[Echo QR] decode succeeded', { attempt: index + 1 });
            return decoded;
        } catch (error) {
            lastError = error;
            console.warn('[Echo QR] decode attempt failed', { attempt: index + 1, error });
        } finally {
            try { await reader.clear(); } catch (_) { /* no active scan to clear */ }
        }
    }
    console.error('[Echo QR] upload reached decoder but all attempts failed', lastError);
    throw lastError;
};
