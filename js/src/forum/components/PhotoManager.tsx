import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type { ApiPayloadSingle } from 'flarum/common/Store';
import type { GarageBuild, GarageEntry, GaragePhoto } from '../../common/models';
import uploadPhoto from '../upload';
import { t } from '../util';

/**
 * The photos of a build or a log entry in a form. Saved photos (attrs.model)
 * are reordered or removed at once; new ones wait in `queue` until the form
 * is saved, then go up one at a time (sendQueue). The first photo is the cover.
 */
interface QueuedPhoto {
  file: File;
  preview: string;
}

export class PhotoQueue {
  files: QueuedPhoto[];
  max: number;

  constructor(max: number) {
    this.max = max;
    this.files = [];
  }

  add(fileList: ArrayLike<File>, saved: number) {
    // FileList is not iterable in the DOM lib this project targets (no dom.iterable).
    for (const file of Array.from(fileList)) {
      if (!/^image\//.test(file.type)) continue;
      if (saved + this.files.length >= this.max) break;
      this.files.push({ file, preview: URL.createObjectURL(file) });
    }
    m.redraw();
  }

  remove(item: QueuedPhoto) {
    URL.revokeObjectURL(item.preview);
    this.files = this.files.filter((f) => f !== item);
  }

  /** Uploads every queued photo to the build or entry at `url`; calls progress(n, total). Returns the names that failed. */
  async send(url: string, progress: (done: number, total: number) => void): Promise<string[]> {
    const failed: string[] = [];
    const files = [...this.files];
    for (let i = 0; i < files.length; i++) {
      progress(i + 1, files.length);
      try {
        await uploadPhoto(files[i].file, url + '/photos');
        this.remove(files[i]);
      } catch (e) {
        failed.push(files[i].file.name);
      }
    }
    return failed;
  }
}

interface PhotoManagerAttrs {
  model?: GarageBuild | GarageEntry | null;
  queue: PhotoQueue;
  endpoint: string | null;
  cover?: boolean;
}

export default class PhotoManager {
  dragging!: boolean;

  view(vnode: Mithril.Vnode<PhotoManagerAttrs, this>) {
    const { model, queue, endpoint, cover = true } = vnode.attrs;
    const saved = model?.photos?.() || [];
    const total = saved.length + queue.files.length;
    const full = total >= queue.max;

    const tile = (src: string, i: number, actions: Mithril.Children) => (
      <li className={'GaragePhotoManager-item' + (cover && i === 0 ? ' is-cover' : '')}>
        <img src={src} alt="" />
        {cover && i === 0 ? <span className="GaragePhotoManager-cover">{t('edit.cover')}</span> : null}
        <span className="GaragePhotoManager-tools">{actions}</span>
      </li>
    );

    return (
      <div
        className={'GaragePhotoManager' + (this.dragging ? ' is-dragging' : '')}
        ondragover={(e: DragEvent) => {
          e.preventDefault();
          this.dragging = true;
        }}
        ondragleave={() => (this.dragging = false)}
        ondrop={(e: DragEvent) => {
          e.preventDefault();
          this.dragging = false;
          queue.add(e.dataTransfer!.files, saved.length);
        }}
      >
        <ul className="GaragePhotoManager-list">
          {saved.map((photo: GaragePhoto, i: number) =>
            tile(photo.small, i, [
              cover && i > 0 ? (
                <Button
                  className="Button Button--icon"
                  icon="fas fa-star"
                  aria-label={extractText(t('edit.make_cover'))}
                  title={extractText(t('edit.make_cover'))}
                  onclick={() => this.order(endpoint!, model!, i, 0)}
                />
              ) : null,
              i > 0 ? (
                <Button
                  className="Button Button--icon"
                  icon="fas fa-arrow-left"
                  aria-label={extractText(t('edit.move_left'))}
                  onclick={() => this.order(endpoint!, model!, i, i - 1)}
                />
              ) : null,
              i < saved.length - 1 ? (
                <Button
                  className="Button Button--icon"
                  icon="fas fa-arrow-right"
                  aria-label={extractText(t('edit.move_right'))}
                  onclick={() => this.order(endpoint!, model!, i, i + 1)}
                />
              ) : null,
              <Button
                className="Button Button--icon"
                icon="far fa-trash-alt"
                aria-label={extractText(t('edit.remove_photo'))}
                onclick={() => this.remove(endpoint!, model!, i)}
              />,
            ])
          )}
          {queue.files.map((item: QueuedPhoto, j: number) =>
            tile(item.preview, saved.length + j, [
              <span className="GaragePhotoManager-pending">{t('edit.pending')}</span>,
              <Button
                className="Button Button--icon"
                icon="fas fa-xmark"
                aria-label={extractText(t('edit.remove_photo'))}
                onclick={() => queue.remove(item)}
              />,
            ])
          )}
          {!full ? (
            <li className="GaragePhotoManager-add">
              <label>
                <Icon name="fas fa-camera" />
                <span>{t('edit.add_photos')}</span>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  multiple
                  onchange={(e: Event) => {
                    const input = e.target as HTMLInputElement;
                    queue.add(input.files!, saved.length);
                    input.value = '';
                  }}
                />
              </label>
            </li>
          ) : null}
        </ul>
        <p className="helpText">{t(cover ? 'edit.photos_help' : 'edit.entry_photos_help', { count: queue.max })}</p>
      </div>
    );
  }

  order(endpoint: string, model: GarageBuild | GarageEntry, from: number, to: number) {
    const order = model.photos()!.map((_, i) => i);
    const [moved] = order.splice(from, 1);
    order.splice(to, 0, moved);
    app.request<ApiPayloadSingle>({ method: 'POST', url: `${endpoint}/photos/order`, body: { order } }).then((payload) => {
      app.store.pushPayload(payload);
      m.redraw();
    });
  }

  remove(endpoint: string, model: GarageBuild | GarageEntry, index: number) {
    if (!confirm(extractText(t('edit.remove_photo_confirm')))) return;
    app.request<ApiPayloadSingle>({ method: 'POST', url: `${endpoint}/photos/remove`, body: { url: model.photos()![index].url } }).then((payload) => {
      app.store.pushPayload(payload);
      m.redraw();
    });
  }
}
