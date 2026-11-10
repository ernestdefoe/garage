import app from 'flarum/forum/app';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import { t } from '../util';

let holder = null;

/** Full-screen photos: arrows, Escape, swipe, and a count. Mounted on its own, outside the page. */
export function openLightbox(photos, index = 0, caption = '') {
  if (!photos?.length) return;
  const opener = document.activeElement;
  holder ??= document.body.appendChild(document.createElement('div'));
  const close = () => {
    m.mount(holder, null);
    document.documentElement.classList.remove('GarageLightbox-open');
    opener?.focus?.();
  };
  document.documentElement.classList.add('GarageLightbox-open');
  m.mount(holder, { view: () => <Lightbox photos={photos} index={index} caption={caption} onclose={close} /> });
}

class Lightbox {
  oninit(vnode) {
    this.index = vnode.attrs.index;
    this.keys = (e) => {
      if (e.key === 'Escape') vnode.attrs.onclose();
      if (e.key === 'ArrowRight') this.go(vnode.attrs, 1);
      if (e.key === 'ArrowLeft') this.go(vnode.attrs, -1);
    };
    window.addEventListener('keydown', this.keys);
  }

  oncreate(vnode) {
    vnode.dom.querySelector('.GarageLightbox-close')?.focus();
  }

  onremove() {
    window.removeEventListener('keydown', this.keys);
  }

  go(attrs, step) {
    const count = attrs.photos.length;
    this.index = (this.index + step + count) % count;
    m.redraw();
  }

  view(vnode) {
    const { photos, caption, onclose } = vnode.attrs;
    const photo = photos[this.index];
    const many = photos.length > 1;

    return (
      <div
        className="GarageLightbox"
        role="dialog"
        aria-modal="true"
        aria-label={caption || extractText(t('build.gallery'))}
        onclick={(e) => {
          if (e.target === e.currentTarget) onclose();
        }}
        onpointerdown={(e) => (this.start = e.clientX)}
        onpointerup={(e) => {
          const moved = e.clientX - (this.start ?? e.clientX);
          if (many && Math.abs(moved) > 50) this.go(vnode.attrs, moved < 0 ? 1 : -1);
          this.start = null;
        }}
      >
        <img className="GarageLightbox-image" src={photo.url} alt="" key={photo.url} />
        <button type="button" className="GarageLightbox-close" aria-label={extractText(t('lightbox.close'))} onclick={onclose}>
          <Icon name="fas fa-xmark" />
        </button>
        {many ? (
          <>
            <button
              type="button"
              className="GarageLightbox-prev"
              aria-label={extractText(t('lightbox.previous'))}
              onclick={() => this.go(vnode.attrs, -1)}
            >
              <Icon name="fas fa-chevron-left" />
            </button>
            <button
              type="button"
              className="GarageLightbox-next"
              aria-label={extractText(t('lightbox.next'))}
              onclick={() => this.go(vnode.attrs, 1)}
            >
              <Icon name="fas fa-chevron-right" />
            </button>
          </>
        ) : null}
        <div className="GarageLightbox-caption">
          {caption ? <span>{caption}</span> : null}
          {many ? <span>{t('lightbox.count', { index: this.index + 1, count: photos.length })}</span> : null}
        </div>
      </div>
    );
  }
}

/** A grid of photos that open in the lightbox. */
export function PhotoGrid({ attrs }) {
  return {
    view() {
      const { photos, caption, limit } = attrs;
      if (!photos?.length) return null;
      const shown = limit ? photos.slice(0, limit) : photos;
      const hidden = photos.length - shown.length;

      return (
        <div className={'GaragePhotos GaragePhotos--n' + Math.min(shown.length, 5)}>
          {shown.map((photo, i) => (
            <button
              type="button"
              className="GaragePhotos-item"
              onclick={() => openLightbox(photos, i, caption)}
              aria-label={extractText(t('lightbox.open', { index: i + 1 }))}
            >
              <img src={photo.small} alt="" loading="lazy" decoding="async" width={photo.w || undefined} height={photo.h || undefined} />
              {hidden && i === shown.length - 1 ? <span className="GaragePhotos-more">+{hidden}</span> : null}
            </button>
          ))}
        </div>
      );
    },
  };
}
