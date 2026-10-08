// Extract the registration independently of country names and other OCR text.
export function normalizePlate(text) {
    const cleaned = String(text).toUpperCase().replace(/[–—]/g, '-');
    const candidates = cleaned.matchAll(/(?<![A-Z0-9])([A-Z]{2})\s*(\d{3,6})(?:[\s-]+(\d{2})|\s*([A-Z])\b)?(?![A-Z0-9])/g);

    for (const [, region, number, year, suffix] of candidates) {
        if (year && number.length === 4) return `${region} ${number}-${year}`;
        if (! year && ! suffix && number.length === 6) return `${region} ${number.slice(0, 4)}-${number.slice(4)}`;
        if (! year && number.length <= 4) return `${region} ${number}${suffix || ''}`;
    }

    return null;
}

// Translate the visible guide back into camera pixels, including object-fit cropping.
export function cameraCrop(videoWidth, videoHeight, videoRect, guideRect) {
    const scale = Math.max(videoRect.width / videoWidth, videoRect.height / videoHeight);
    const offsetX = (videoWidth * scale - videoRect.width) / 2;
    const offsetY = (videoHeight * scale - videoRect.height) / 2;
    const x = Math.max(0, (guideRect.left - videoRect.left + offsetX) / scale);
    const y = Math.max(0, (guideRect.top - videoRect.top + offsetY) / scale);

    return {
        x, y,
        width: Math.min(guideRect.width / scale, videoWidth - x),
        height: Math.min(guideRect.height / scale, videoHeight - y),
    };
}
