/* Kept as a no-build fallback for the public flight-plan page. */
(async () => {
    const exif = async (file) => {
        if (!/^image\/jpe?g$/i.test(file.type)) return 1;
        const b = new DataView(await file.arrayBuffer());
        if (b.getUint16(0, false) !== 0xffd8) return 1;
        let p = 2;
        while (p + 4 <= b.byteLength) {
            const marker = b.getUint16(p, false); p += 2;
            const length = b.getUint16(p, false);
            if (marker === 0xffe1 && b.getUint32(p + 2, false) === 0x45786966) {
                const t = p + 8, le = b.getUint16(t, false) === 0x4949;
                const u16 = (x) => b.getUint16(x, le), u32 = (x) => b.getUint32(x, le);
                const ifd = t + u32(t + 4);
                for (let i = 0; i < u16(ifd); i++) if (u16(ifd + 2 + i * 12) === 0x0112) return u16(ifd + 10 + i * 12);
                return 1;
            }
            p += length;
        }
        return 1;
    };
    const normalize = async (file) => {
        let source, manual = false;
        try { source = window.createImageBitmap && await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch (_) {}
        if (!source) {
            manual = true;
            source = await new Promise((resolve, reject) => { const i = new Image(); i.onload = () => resolve(i); i.onerror = reject; i.src = URL.createObjectURL(file); });
        }
        const w = source.width || source.naturalWidth, h = source.height || source.naturalHeight;
        const o = manual ? await exif(file) : 1, swap = o >= 5 && o <= 8;
        const c = document.createElement('canvas'), x = c.getContext('2d', { willReadFrequently: true });
        c.width = swap ? h : w; c.height = swap ? w : h;
        const transforms = { 2: [-1,0,0,1,w,0], 3: [-1,0,0,-1,w,h], 4: [1,0,0,-1,0,h], 5: [0,1,1,0,0,0], 6: [0,1,-1,0,h,0], 7: [0,-1,-1,0,h,w], 8: [0,-1,1,0,0,w] };
        x.setTransform(...(transforms[o] || [1,0,0,1,0,0])); x.drawImage(source, 0, 0, w, h); x.setTransform(1,0,0,1,0,0);
        if (source.close) source.close();
        return c;
    };
    const blobFile = (canvas, name) => new Promise((resolve, reject) => canvas.toBlob((b) => b ? resolve(new File([b], name, { type: 'image/png' })) : reject(new Error('Canvas serialization failed')), 'image/png'));
    window.EchoQrImageDecoder = async (file, region, Decoder) => {
        console.info('[Echo QR] upload received', { name: file.name, type: file.type || 'unknown', size: file.size });
        const canvas = await normalize(file), variants = [canvas], gray = document.createElement('canvas');
        gray.width = canvas.width; gray.height = canvas.height; const gx = gray.getContext('2d', { willReadFrequently: true }); gx.filter = 'grayscale(1) contrast(1.15)'; gx.drawImage(canvas, 0, 0); variants.push(gray);
        let last;
        for (let i = 0; i < variants.length; i++) { const reader = new Decoder(region, { verbose: true }); try { console.info('[Echo QR] normalized image', { width: canvas.width, height: canvas.height, decoder: 'html5-qrcode scanFile -> ZXing', attempt: i + 1 }); return await reader.scanFile(await blobFile(variants[i], `echo-qr-${i}.png`), false); } catch (e) { last = e; console.warn('[Echo QR] decode attempt failed', { attempt: i + 1, error: e }); } finally { try { await reader.clear(); } catch (_) {} } }
        console.error('[Echo QR] upload reached decoder but all attempts failed', last); throw last;
    };
})();
