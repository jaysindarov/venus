/**
 * Format a number with locale-aware thousands separators.
 */
export function formatNumber(value) {
    return new Intl.NumberFormat().format(value)
}

/**
 * Truncate a string to maxLength, appending ellipsis if needed.
 */
export function truncate(str, maxLength = 100) {
    if (str.length <= maxLength) return str
    return str.slice(0, maxLength).trimEnd() + '…'
}

/**
 * Resolve an aspect ratio string (e.g. '16:9') to pixel dimensions.
 */
export function aspectRatioDimensions(ratio, baseSize = 1024) {
    const [w, h] = ratio.split(':').map(Number)
    if (!w || !h) return { width: baseSize, height: baseSize }
    const scale = baseSize / Math.max(w, h)
    return {
        width: Math.round(w * scale),
        height: Math.round(h * scale),
    }
}
