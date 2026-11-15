import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import Comments from './Comments';
import { PhotoGrid } from './Lightbox';
import { openEntryEditor } from './openers';
import richText from '../text';
import type Mithril from 'mithril';
import type { GarageBuild, GarageEntry } from '../../common/models';
import type { LogState } from '../types';
import { t, money } from '../util';

/** "Mar 4, 2026", from the entry's date (a day, no time zone). */
function day(date: string | null) {
  const [y, mo, d] = String(date).slice(0, 10).split('-').map(Number);
  return new Date(y, mo - 1, d);
}

/**
 * The build log: newest first, as a timeline. Each entry has its photos, its
 * cost and its own comments (folded until opened).
 */
interface BuildLogAttrs {
  build: GarageBuild;
  state: LogState;
}

export default class BuildLog {
  loading!: boolean;
  open!: Set<string>;

  oninit() {
    this.open = new Set();
    const wanted = m.route.param('entry');
    if (wanted) this.open.add(String(wanted));
  }

  oncreate(vnode: Mithril.VnodeDOM<BuildLogAttrs, this>) {
    const wanted = m.route.param('entry');
    const node = wanted && vnode.dom.querySelector(`[data-entry="${CSS.escape(wanted)}"]`);
    if (node) setTimeout(() => node.scrollIntoView({ block: 'start', behavior: 'smooth' }), 300);
  }

  view(vnode: Mithril.Vnode<BuildLogAttrs, this>) {
    const { build, state } = vnode.attrs;
    const entries = state.entries;

    if (!entries.length) {
      return (
        <div className="GarageLog-empty">
          <Icon name="fas fa-clock-rotate-left" />
          <p>{t(build.canEdit() ? 'build.no_updates_own' : 'build.no_updates')}</p>
          {build.canEdit() ? (
            <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => openEntryEditor({ build, log: state })}>
              {t('build.add_update')}
            </Button>
          ) : null}
        </div>
      );
    }

    return (
      <div className="GarageLog">
        <ol className="GarageLog-list">{entries.map((entry) => this.entry(entry, build, state))}</ol>
        {state.moreEntries ? (
          <div className="GarageLog-more">
            <Button className="Button" loading={this.loading} onclick={() => this.loadMore(build, state)}>
              {t('build.older_updates')}
            </Button>
          </div>
        ) : null}
      </div>
    );
  }

  entry(entry: GarageEntry, build: GarageBuild, state: LogState) {
    const date = day(entry.happenedOn());
    const id = entry.id()!; // a saved entry always has an id
    const open = this.open.has(id);
    const count = entry.commentCount() || 0;

    return (
      <li className="GarageLog-entry" key={entry.id()} data-entry={entry.id()}>
        <div className="GarageLog-date" aria-hidden="true">
          <span className="GarageLog-month">{dayjs(date).format('MMM')}</span>
          <span className="GarageLog-day">{dayjs(date).format('D')}</span>
          <span className="GarageLog-year">{dayjs(date).format('YYYY')}</span>
        </div>
        <article className="GarageLog-card">
          <header className="GarageLog-header">
            <h3 className="GarageLog-title">{entry.title()}</h3>
            <time className="GarageLog-when" datetime={entry.happenedOn()}>
              {dayjs(date).format('LL')}
            </time>
            {entry.cost() ? (
              <span className="GarageLog-cost">
                <Icon name="fas fa-coins" /> {money(entry.cost())}
              </span>
            ) : null}
          </header>
          {entry.body() || entry.bodyHtml() ? <div className="GarageLog-body">{richText(entry.body(), entry.bodyHtml())}</div> : null}
          <PhotoGrid photos={entry.photos()} caption={entry.title()} limit={5} />
          <footer className="GarageLog-footer">
            {build.published() ? (
              <Button
                className="Button Button--link"
                icon="far fa-comment"
                aria-expanded={open ? 'true' : 'false'}
                onclick={() => (open ? this.open.delete(id) : this.open.add(id))}
              >
                {count ? t('entry.comments', { count }) : t('entry.reply')}
              </Button>
            ) : null}
            {entry.canEdit() ? (
              <>
                <Button className="Button Button--link" icon="fas fa-pen" onclick={() => openEntryEditor({ build, entry, log: state })}>
                  {t('entry.edit')}
                </Button>
                <Button className="Button Button--link" icon="far fa-trash-alt" onclick={() => this.remove(entry, build, state)}>
                  {t('entry.delete')}
                </Button>
              </>
            ) : null}
          </footer>
          {open && build.published() ? <Comments build={build} entry={entry} state={state} /> : null}
        </article>
      </li>
    );
  }

  loadMore(build: GarageBuild, state: LogState) {
    this.loading = true;
    app.store
      .find<GarageEntry[]>('garage-entries', { filter: { build: build.id() }, page: { offset: state.entries.length, limit: 20, total: 0 } })
      .then((more) => {
        state.entries = [...state.entries, ...more];
        state.moreEntries = !!more.payload?.links?.next;
      })
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  remove(entry: GarageEntry, build: GarageBuild, state: LogState) {
    if (!confirm(extractText(t('entry.delete_confirm', { title: entry.title() })))) return;
    entry.delete().then(() => {
      state.entries = state.entries.filter((e) => e !== entry);
      // The build's counts, latest entry and spend were worked out again on the server.
      app.store.find<GarageBuild>('garage-builds', build.id()!);
      m.redraw();
    });
  }
}
