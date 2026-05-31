/** Matches App\Support\PostCoverImageStorage::MAX_WIDTH */
export const BLOG_COVER_MAX_WIDTH = 800;

/**
 * Scale down width before upload so the request stays under PHP post limits.
 * Only runs when wider than maxWidth; does not recompress smaller images.
 *
 * @param {File} file
 * @param {number} maxWidth
 * @returns {Promise<File>}
 */
export async function resizeImageFileToMaxWidth(file, maxWidth = BLOG_COVER_MAX_WIDTH) {
    if (!(file instanceof File) || !file.type.startsWith('image/')) {
        return file;
    }

    let bitmap;
    try {
        bitmap = await createImageBitmap(file);
    } catch {
        return file;
    }

    const { width, height } = bitmap;

    if (width <= maxWidth) {
        bitmap.close();
        return file;
    }

    const targetW = maxWidth;
    const targetH = Math.max(1, Math.round((height * maxWidth) / width));

    const canvas = document.createElement('canvas');
    canvas.width = targetW;
    canvas.height = targetH;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        bitmap.close();
        return file;
    }

    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(bitmap, 0, 0, targetW, targetH);
    bitmap.close();

    const mime = file.type === 'image/png' ? 'image/png' : file.type === 'image/webp' ? 'image/webp' : 'image/jpeg';
    const quality = mime === 'image/jpeg' ? 0.92 : mime === 'image/webp' ? 0.92 : undefined;

    const blob = await new Promise((resolve, reject) => {
        canvas.toBlob(
            (result) => (result ? resolve(result) : reject(new Error('Image encode failed'))),
            mime,
            quality,
        );
    });

    const extension = mime === 'image/png' ? 'png' : mime === 'image/webp' ? 'webp' : 'jpg';
    const baseName = (file.name || 'cover').replace(/\.[^.]+$/, '') || 'cover';

    return new File([blob], `${baseName}.${extension}`, {
        type: mime,
        lastModified: Date.now(),
    });
}
