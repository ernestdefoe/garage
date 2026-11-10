import app from 'flarum/forum/app';

/**
 * Sends one photo to a build or a log entry (`url` is its photos endpoint).
 * With FoF Upload chosen by the admin, the file goes there first and Garage
 * is told its uuid; otherwise it goes to Garage's own storage. Big phone
 * photos are shrunk in the browser first, so they fit the size limit and
 * upload quickly.
 */
export default async function uploadPhoto(file, url) {
  const photo = await shrink(file);
  let body = new FormData();

  if (app.forum.attribute('garageUploads') === 'fof') {
    const upload = new FormData();
    upload.append('files[]', photo, photo.name);
    const response = await app.request({ method: 'POST', url: app.forum.attribute('apiUrl') + '/fof/upload', serialize: (raw) => raw, body: upload });
    const uuid = (response.data || [])[0]?.attributes?.uuid;
    if (!uuid) throw new Error('upload');
    body = { fofUuid: uuid };
  } else {
    body.append('photo', photo, photo.name);
  }

  const payload = await app.request({
    method: 'POST',
    url,
    serialize: body instanceof FormData ? (raw) => raw : undefined,
    body,
  });

  return app.store.pushPayload(payload);
}

const MAX_SIDE = 2560;

/** A JPEG no bigger than MAX_SIDE, when the photo is larger than that or than half the size limit. */
async function shrink(file) {
  const limit = (app.forum.attribute('garageMaxUploadKb') || 10240) * 1024;
  if (!/^image\/(jpeg|png|webp)$/.test(file.type) || typeof createImageBitmap !== 'function') return file;

  try {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
    if (scale === 1 && file.size <= limit / 2) return file;

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.88));

    return blob && blob.size < file.size ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
  } catch (e) {
    return file;
  }
}
