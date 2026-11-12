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
        {/* Keyed on its own: a new photo is a new image, never the old one until it loads. */}
        {[<img className="GarageLightbox-image" src={photo.url} alt="" key={photo.url} />]}
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

/**
 * Photos that open full screen: a grid, or the build's cover (limit 1, `cover`).
 * Every photo is a link, the ones past the limit hidden, so the viewer pages
 * through them all. With FoF PhotoSwipe on, its viewer takes these links, as it
 * does a post's images; otherwise Garage's own does.
 */
export class PhotoGrid {
  view(vnode) {
    const { photos, caption, limit, cover } = vnode.attrs;
    if (!photos?.length) return null;
    const shown = Math.min(limit || photos.length, photos.length);
    const hidden = photos.length - shown;
    const className = cover ? 'GarageBuildHero-cover' : 'GaragePhotos GaragePhotos--n' + Math.min(shown, 5);

    return (
      <div className={'GarageGallery ' + className}>
        {photos.map((photo, i) => (
          <a
            href={photo.url}
            className={cover ? null : 'GaragePhotos-item'}
            hidden={i >= shown}
            data-pswp=""
            data-pswp-width={photo.w || undefined}
            data-pswp-height={photo.h || undefined}
            onclick={(e) => {
              if (photoswipe || e.ctrlKey || e.metaKey || e.shiftKey) return;
              e.preventDefault();
              openLightbox(photos, i, caption);
            }}
            aria-label={extractText(t('lightbox.open', { index: i + 1 }))}
          >
            {i < shown ? (
              <img
                src={cover ? photo.url : photo.small}
                alt=""
                loading={cover ? undefined : 'lazy'}
                decoding="async"
                width={photo.w || undefined}
                height={photo.h || undefined}
              />
            ) : null}
            {hidden && !cover && i === shown - 1 ? <span className="GaragePhotos-more">+{hidden}</span> : null}
          </a>
        ))}
      </div>
    );
  }
}

// Read when this chunk loads: by then every enabled extension has booted.
const photoswipe = flarum.extensions['fof-photoswipe']?.Gallery;
if (photoswipe) new photoswipe(PhotoGrid, '.GarageGallery').extend(app, { name: 'ernestdefoe-garage', exports: {} });
