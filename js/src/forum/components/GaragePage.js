import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import extractText from 'flarum/common/utils/extractText';
import BuildCard from './BuildCard';
import useStyles from '../styles';
import { t, STAGES, categoryColor } from '../util';
import { openBuildEditor } from './openers';

const SORTS = {
  recent: '-lastActivityAt',
  newest: '-publishedAt',
  liked: '-likeCount',
  followed: '-followerCount',
  updates: '-entryCount',
};

/**
 * /garage: every published build, newest activity first. Categories are chips
 * across the top; sort, stage, the category's filter fields and a text search
 * narrow the grid. Filters live in the address (replaced, not pushed, so the
 * page is not rebuilt while someone types).
 */
export default class GaragePage extends Page {
  oninit(vnode) {
    super.oninit(vnode);
    useStyles();
    app.setTitle(extractText(t('page.title')));
    app.setTitleCount(0);

    const param = (key) => m.route.param(key) || '';
    this.filters = {
      category: param('category'),
      sort: SORTS[param('sort')] ? param('sort') : 'recent',
      stage: param('stage'),
      text: param('q'),
      spec: param('spec'),
    };
    this.categories = app.store.all('garage-categories');
    this.builds = [];
    this.featured = [];
    this.loading = true;
    this.more = false;

    const categories = this.categories.length ? Promise.resolve(this.categories) : app.store.find('garage-categories');
    categories.then((list) => {
      this.categories = [...list].sort((a, b) => a.position() - b.position());
      this.refresh();
    });

    if (!this.filtered()) {
      app.store
        .find('garage-builds', { filter: { featured: '1', published: '1' }, sort: '-featuredAt', page: { limit: 4, total: 0 } })
        .then((builds) => {
          this.featured = builds;
          m.redraw();
        });
    }
  }

  category() {
    return this.categories.find((c) => c.slug() === this.filters.category) || null;
  }

  filtered() {
    const f = this.filters;
    return !!(f.category || f.stage || f.text || f.spec || f.sort !== 'recent');
  }

  query(offset = 0) {
    const f = this.filters;
    const filter = { published: '1' };
    const category = this.category();
    if (category) filter.category = category.id();
    if (f.stage) filter.stage = f.stage;
    if (f.text.trim()) filter.text = f.text.trim();
    if (f.spec && category) filter.spec = f.spec;

    return { filter, sort: SORTS[f.sort], page: { offset, limit: 24, total: 0 } };
  }

  refresh() {
    this.loading = true;
    this.builds = [];
    const ticket = (this.ticket = {});
    m.redraw();

    return app.store.find('garage-builds', this.query()).then((builds) => {
      if (ticket !== this.ticket) return;
      this.builds = builds;
      this.more = !!builds.payload?.links?.next;
      this.loading = false;
      m.redraw();
    });
  }

  loadMore() {
    this.loadingMore = true;
    app.store.find('garage-builds', this.query(this.builds.length)).then((builds) => {
      this.builds = [...this.builds, ...builds];
      this.more = !!builds.payload?.links?.next;
      this.loadingMore = false;
      m.redraw();
    });
  }

  /** Change a filter: the address follows, the grid reloads. */
  set(changes) {
    Object.assign(this.filters, changes);
    if ('category' in changes) this.filters.spec = '';
    const f = this.filters;
    const params = { category: f.category, sort: f.sort === 'recent' ? '' : f.sort, stage: f.stage, q: f.text, spec: f.spec };
    window.history.replaceState(window.history.state, '', app.route('garage', params));

    clearTimeout(this.typing);
    if ('text' in changes) this.typing = setTimeout(() => this.refresh(), 350);
    else this.refresh();
  }

  view() {
    return (
      <PageStructure className="GaragePage" hero={() => this.hero()} sidebar={() => <IndexSidebar />}>
        {this.chips()}
        {this.toolbar()}
        {this.featured.length && !this.filtered() ? (
          <section className="GaragePage-featured" aria-label={extractText(t('page.featured'))}>
            <h2 className="GaragePage-heading">
              <Icon name="fas fa-star" /> {t('page.featured')}
            </h2>
            <div className="GarageGrid GarageGrid--featured">
              {this.featured.map((build) => (
                <BuildCard build={build} large />
              ))}
            </div>
          </section>
        ) : null}
        {this.grid()}
      </PageStructure>
    );
  }

  hero() {
    const category = this.category();
    const canCreate = app.forum.attribute('garageCanCreate');

    return (
      <header className="GarageHero" style={{ '--garage-accent': categoryColor(category?.color()) }}>
        <div className="container">
          <div className="GarageHero-text">
            <h1 className="GarageHero-title">
              <Icon name={category?.icon() || 'fas fa-warehouse'} /> {category ? category.name() : t('page.title')}
            </h1>
            <p className="GarageHero-subtitle">{category?.description() || t('page.subtitle')}</p>
          </div>
          {canCreate || !app.session.user ? (
            <Button
              className="Button Button--primary GarageHero-add"
              icon="fas fa-plus"
              onclick={() => (app.session.user ? openBuildEditor({ category }) : app.modal.show(() => import('flarum/forum/components/LogInModal')))}
            >
              {t('page.add')}
            </Button>
          ) : null}
        </div>
      </header>
    );
  }

  chips() {
    if (this.categories.length < 2) return null;
    const chip = (category) => {
      const active = (category?.slug() || '') === this.filters.category;
      return (
        <button
          type="button"
          className={'GarageChip' + (active ? ' active' : '')}
          aria-pressed={active ? 'true' : 'false'}
          style={{ '--garage-accent': categoryColor(category?.color()) }}
          onclick={() => this.set({ category: category?.slug() || '' })}
        >
          <Icon name={category ? category.icon() || 'fas fa-warehouse' : 'fas fa-border-all'} />
          <span>{category ? category.name() : t('page.all')}</span>
          {category ? <span className="GarageChip-count">{category.buildCount()}</span> : null}
        </button>
      );
    };

    return (
      <nav className="GarageChips" aria-label={extractText(t('page.categories'))}>
        {chip(null)}
        {this.categories.map(chip)}
      </nav>
    );
  }

  toolbar() {
    const f = this.filters;
    const sorts = {};
    Object.keys(SORTS).forEach((key) => (sorts[key] = extractText(t(`page.sort.${key}`))));
    const stages = { '': extractText(t('page.stage_any')) };
    STAGES.forEach((stage) => (stages[stage] = extractText(t(`stage.${stage}`))));
    const filterFields = (this.category()?.fields() || []).filter((field) => field.type === 'select' && field.filter && field.options?.length);
    const [specKey, specValue] = f.spec ? [f.spec.slice(0, f.spec.indexOf(':')), f.spec.slice(f.spec.indexOf(':') + 1)] : ['', ''];

    return (
      <div className="GarageToolbar">
        <label className="GarageToolbar-search">
          <Icon name="fas fa-magnifying-glass" />
          <input
            className="FormControl"
            type="search"
            value={f.text}
            placeholder={extractText(t('page.search'))}
            aria-label={extractText(t('page.search'))}
            oninput={(e) => this.set({ text: e.target.value })}
          />
        </label>
        <div className="GarageToolbar-selects">
          <Select options={sorts} value={f.sort} onchange={(sort) => this.set({ sort })} aria-label={extractText(t('page.sort.label'))} />
          <Select options={stages} value={f.stage} onchange={(stage) => this.set({ stage })} aria-label={extractText(t('page.stage_label'))} />
          {filterFields.map((field) => {
            const options = { '': extractText(t('page.spec_any', { label: field.label })) };
            field.options.forEach((option) => (options[option] = option));
            return (
              <Select
                options={options}
                value={specKey === field.key ? specValue : ''}
                onchange={(value) => this.set({ spec: value ? `${field.key}:${value}` : '' })}
                aria-label={field.label}
              />
            );
          })}
        </div>
      </div>
    );
  }

  grid() {
    if (this.loading) return <LoadingIndicator display="block" />;

    if (!this.builds.length) {
      return (
        <div className="GarageEmpty">
          <Icon name="fas fa-warehouse" />
          <p>{t(this.filtered() ? 'page.empty_filtered' : 'page.empty')}</p>
          {!this.filtered() && app.forum.attribute('garageCanCreate') ? (
            <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => openBuildEditor({ category: this.category() })}>
              {t('page.empty_cta')}
            </Button>
          ) : null}
        </div>
      );
    }

    return (
      <section aria-label={extractText(t('page.title'))}>
        {this.featured.length && !this.filtered() ? <h2 className="GaragePage-heading">{t('page.latest')}</h2> : null}
        <div className="GarageGrid">
          {this.builds.map((build) => (
            <BuildCard build={build} />
          ))}
        </div>
        {this.more ? (
          <div className="GaragePage-more">
            <Button className="Button" loading={this.loadingMore} onclick={() => this.loadMore()}>
              {t('page.load_more')}
            </Button>
          </div>
        ) : null}
      </section>
    );
  }
}
