export function createCameraZoom(video, track, onChange) {
    let capability;
    try { capability = track?.getCapabilities?.().zoom; } catch { /* Digital zoom is available without camera capabilities. */ }
    const native = capability && Number.isFinite(capability.min) && capability.min > 0 && capability.max > capability.min;
    const zoom = {
        min: native ? capability.min : 1,
        max: native ? capability.max : 4,
        step: native ? (capability.step || 0.1) : 0.1,
        value: native ? (track.getSettings?.().zoom || capability.min) : 1,
        native: Boolean(native),
    };
    let baseline = 1;
    let desired = zoom.value;
    let pending = null;
    video.style.transform = 'scale(1)';

    function clamp(value) {
        const bounded = Math.min(zoom.max, Math.max(zoom.min, Number(value) || zoom.min));
        return Math.min(zoom.max, Number((zoom.min + Math.round((bounded - zoom.min) / zoom.step) * zoom.step).toFixed(4)));
    }

    zoom.set = (value) => {
        desired = clamp(value);
        if (pending) return pending;
        pending = (async () => {
            do {
                const target = desired;
                if (zoom.native) {
                    try {
                        await track.applyConstraints({ advanced: [{ zoom: target }] });
                        zoom.value = track.getSettings?.().zoom ?? target;
                    } catch {
                        // Retain any camera zoom already applied, then enlarge its preview.
                        baseline = zoom.value;
                        zoom.native = false;
                        zoom.min = baseline;
                        zoom.max = baseline * 4;
                        zoom.step = 0.1;
                        desired = clamp(desired);
                        zoom.value = clamp(target);
                        video.style.transform = `scale(${zoom.value / baseline})`;
                    }
                } else {
                    zoom.value = target;
                    video.style.transform = `scale(${zoom.value / baseline})`;
                }
                onChange(zoom);
                if (target === desired) break;
            } while (true);
        })().finally(() => { pending = null; });
        return pending;
    };

    onChange(zoom);
    return zoom;
}
