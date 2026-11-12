import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import Separator from 'flarum/common/components/Separator';
import Placeholder from 'flarum/common/components/Placeholder';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import ItemList from 'flarum/common/utils/ItemList';
import listItems from 'flarum/common/helpers/listItems';
import extractText from 'flarum/common/utils/extractText';
import BuildLog from './BuildLog';
import Comments from './Comments';
import { PhotoGrid } from './Lightbox';
import { formatSpec } from './BuildCard';
import { openBuildEditor, openEntryEditor, postAbout } from './openers';
import useStyles from '../styles';
import richText from '../text';
import { t, buildUrl, money, categoryColor, STAGE_ICONS } from '../util';

const logIn = () => app.modal.show(() => import('flarum/forum/components/LogInModal'));

/**
 * A build's page, laid out like a discussion: the cover as the hero, the
 * actions and the spec sheet in the sidebar, then the story, the gallery, the
 * build log and the comments. The build, its log and its comments are three
 * requests sent at once.
 */
export default class BuildPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);
    useStyles();
    this.build = null;
    this.state = { entries: [], comments: [], moreEntries: false };
    this.load();
  }

  load() {
    const id = String(m.route.param('id')).split('-')[0];
    const build = app.store.find('garage-builds', id);
    const entries = app.store.find('garage-entries', { filter: { build: id }, page: { limit: 20, total: 0 } });
    const comments = app.store.find('garage-comments', { filter: { build: id }, page: { limit: 200, total: 0 } });

    build.then(
      (found) => {
        this.build = found;
        app.setTitle(found.title());
        app.setTitleCount(0);
        // The address carries the slug, like a discussion's.
        if (m.route.param('id') !== `${found.id()}-${found.slug()}`) {
          window.history.replaceState(window.history.state, '', buildUrl(found) + window.location.search);
        }
        m.redraw();
      },
      () => {
        this.missing = true;
        m.redraw();
      }
    );

    Promise.all([entries, comments]).then(
      ([entryList, commentList]) => {
        this.state.entries = entryList;
        this.state.moreEntries = !!entryList.payload?.links?.next;
        this.state.comments = commentList;
        this.state.loaded = true;
        m.redraw();
      },
      () => {}
    );
  }

  view() {
    if (this.missing) {
      return (
        <PageStructure className="GarageBuildPage">
          <Placeholder text={t('build.not_found')} />
        </PageStructure>
      );
    }

    const build = this.build;

    return (
      <PageStructure className="GarageBuildPage" loading={!build} hero={() => this.hero()} sidebar={() => this.sidebar()}>
        {build ? this.content() : null}
      </PageStructure>
    );
  }

  hero() {
    const build = this.build;
    const cover = build.cover();
    const category = build.category();
    const user = build.user();

    return (
      <header
        className={'GarageBuildHero' + (cover ? '' : ' GarageBuildHero--plain')}
        style={{ '--garage-accent': categoryColor(category?.color()) }}
      >
        {cover ? <PhotoGrid photos={build.photos()} caption={build.title()} limit={1} cover /> : null}
        <div className="GarageBuildHero-shade" />
        <div className="container GarageBuildHero-content">
          <div className="GarageBuildHero-badges">
            {category ? (
              <Link className="GarageBuildHero-category" href={app.route('garage', { category: category.slug() })}>
                <Icon name={category.icon() || 'fas fa-warehouse'} /> {category.name()}
              </Link>
            ) : null}
            <span className={`GarageStage GarageStage--${build.stage()}`}>
              <Icon name={STAGE_ICONS[build.stage()]} /> {t(`stage.${build.stage()}`)}
            </span>
            {build.featured() ? (
              <span className="GarageStage GarageStage--featured">
                <Icon name="fas fa-star" /> {t('page.featured')}
              </span>
            ) : null}
          </div>
          <h1 className="GarageBuildHero-title">{build.title()}</h1>
          {build.tagline() ? <p className="GarageBuildHero-tagline">{build.tagline()}</p> : null}
          {user ? (
            <Link className="GarageBuildHero-owner" href={app.route.user(user)}>
              <Avatar user={user} /> <span>{t('card.by', { username: user.displayName() })}</span>
            </Link>
          ) : null}
        </div>
      </header>
    );
  }

  sidebar() {
    return (
      <nav className="GarageBuildPage-nav">
        <ul>{listItems(this.sidebarItems().toArray())}</ul>
      </nav>
    );
  }

  sidebarItems() {
    const build = this.build;
    const items = new ItemList();

    if (build.canEdit()) {
      items.add(
        'update',
        <Button className="Button Button--primary App-primaryControl" icon="fas fa-plus" onclick={() => openEntryEditor({ build, log: this.state })}>
          {t('build.add_update')}
        </Button>,
        100
      );
    } else if (build.canFollow() || (!app.session.user && build.published())) {
      items.add('follow', this.followButton('Button--primary App-primaryControl'), 100);
    }

    items.add('actions', <div className="GarageActions">{this.actionItems().toArray()}</div>, 90);
    items.add('specs', this.specSheet(), 80);
    items.add('stats', this.stats(), 70);

    return items;
  }

  followButton(className = '') {
    const build = this.build;
    const following = build.followed();

    return (
      <Button
        className={'Button ' + className + (following ? ' GarageButton--on' : '')}
        icon={following ? 'fas fa-bell' : 'far fa-bell'}
        aria-pressed={following ? 'true' : 'false'}
        onclick={() => (app.session.user ? build.save({ followed: !following }) : logIn())}
      >
        {t(following ? 'build.following' : 'build.follow')}
      </Button>
    );
  }

  actionItems() {
    const build = this.build;
    const items = new ItemList();
    const liked = build.liked();

    if (build.canLike() || build.likeCount()) {
      items.add(
        'like',
        <Button
          className={'Button GarageLike' + (liked ? ' GarageButton--on' : '')}
          icon={liked ? 'fas fa-heart' : 'far fa-heart'}
          // A guest is asked to log in; the owner can't like their own build.
          disabled={!build.canLike() && !!app.session.user}
          aria-pressed={liked ? 'true' : 'false'}
          onclick={() => (app.session.user ? build.save({ liked: !liked }) : logIn())}
        >
          {t(liked ? 'build.liked' : 'build.like')} <span className="Button-badge">{build.likeCount()}</span>
        </Button>,
        100
      );
    }

    if (build.canEdit() && build.canFollow()) items.add('follow', this.followButton(), 90);

    if (build.published()) {
      items.add(
        'share',
        <Dropdown
          className="GarageShare"
          buttonClassName="Button"
          icon="fas fa-share-nodes"
          label={t('build.share')}
          accessibleToggleLabel={extractText(t('build.share'))}
        >
          <Button icon="fas fa-link" onclick={() => this.copyLink()}>
            {t('build.copy_link')}
          </Button>
          {app.session.user && app.forum.attribute('canStartDiscussion') ? (
            <Button icon="fas fa-pen-to-square" onclick={() => postAbout(build)}>
              {t('build.discuss')}
            </Button>
          ) : null}
        </Dropdown>,
        80
      );
    }

    const controls = this.controls().toArray();
    if (controls.length) {
      items.add(
        'controls',
        <Dropdown
          className="GarageControls"
          buttonClassName="Button"
          icon="fas fa-ellipsis"
          label={t('build.manage')}
          accessibleToggleLabel={extractText(t('build.manage'))}
        >
          {controls}
        </Dropdown>,
        70
      );
    }

    return items;
  }

  controls() {
    const build = this.build;
    const items = new ItemList();

    if (build.canEdit()) {
      items.add(
        'edit',
        <Button icon="fas fa-pen" onclick={() => openBuildEditor({ build })}>
          {t('build.edit')}
        </Button>,
        100
      );
      if (build.published()) {
        items.add(
          'unpublish',
          <Button icon="fas fa-eye-slash" onclick={() => build.save({ published: false })}>
            {t('build.unpublish')}
          </Button>,
          60
        );
      }
    }

    // With FoF Signature: the owner's build shown under their posts.
    if (build.inSignature() !== undefined && 'fof-signature' in (flarum.extensions || {}) && build.published()) {
      items.add(
        'signature',
        <Button icon="fas fa-signature" onclick={() => build.save({ inSignature: !build.inSignature() })}>
          {t(build.inSignature() ? 'build.signature_off' : 'build.signature_on')}
        </Button>,
        80
      );
    }

    if (build.canFeature()) {
      items.add(
        'feature',
        <Button icon="fas fa-star" onclick={() => build.save({ featured: !build.featured() })}>
          {t(build.featured() ? 'build.unfeature' : 'build.feature')}
        </Button>,
        70
      );
    }

    if (build.canDelete()) {
      items.add('separator', <Separator />, 20);
      items.add(
        'delete',
        <Button icon="far fa-trash-alt" onclick={() => this.remove()}>
          {t('build.delete')}
        </Button>,
        10
      );
    }

    return items;
  }

  specSheet() {
    const build = this.build;
    const fields = build.category()?.fields() || [];
    const specs = build.specs() || {};
    const rows = fields.filter((field) => specs[field.key] !== undefined && specs[field.key] !== '');

    if (!rows.length) return null;

    return (
      <section className="GarageSpecSheet" aria-label={extractText(t('build.specs'))}>
        <h3 className="GarageSidebar-heading">
          <Icon name="fas fa-list-ul" /> {t('build.specs')}
        </h3>
        <dl>
          {rows.map((field) => [
            <dt>{field.label}</dt>,
            <dd>
              {field.type === 'link' && /^https?:\/\//i.test(String(specs[field.key])) ? (
                <a href={specs[field.key]} target="_blank" rel="ugc noopener nofollow">
                  {String(specs[field.key])
                    .replace(/^https?:\/\/(www\.)?/, '')
                    .replace(/\/$/, '')}
                </a>
              ) : (
                formatSpec(field, specs[field.key])
              )}
            </dd>,
          ])}
        </dl>
      </section>
    );
  }

  stats() {
    const build = this.build;
    const stat = (icon, label, value) => (
      <div className="GarageStats-item">
        <dt>
          <Icon name={icon} /> {label}
        </dt>
        <dd>{value}</dd>
      </div>
    );

    return (
      <dl className="GarageStats">
        {stat('far fa-calendar', t('build.stats.started'), dayjs(build.publishedAt() || build.createdAt()).format('ll'))}
        {stat('fas fa-list-check', t('build.stats.updates'), build.entryCount())}
        {build.spent() ? stat('fas fa-coins', t('build.stats.spent'), money(build.spent())) : null}
        {stat('fas fa-bell', t('build.stats.followers'), build.followerCount())}
        {stat('fas fa-heart', t('build.stats.likes'), build.likeCount())}
      </dl>
    );
  }

  content() {
    const build = this.build;
    const photos = build.photos() || [];

    return (
      <div className="GarageBuildPage-content">
        {!build.published() && build.canEdit() ? (
          <div className="GarageDraft">
            <Icon name="fas fa-eye-slash" />
            <p>{t('build.draft_hint')}</p>
            <Button className="Button Button--primary" onclick={() => build.save({ published: true })}>
              {t('build.publish')}
            </Button>
          </div>
        ) : null}

        {build.story() ? (
          <section className="GarageSection GarageStory">
            <h2 className="GarageSection-title">{t('build.story')}</h2>
            <div className="GarageStory-text">{richText(build.story())}</div>
          </section>
        ) : build.canEdit() ? (
          <button type="button" className="GarageNudge" onclick={() => openBuildEditor({ build })}>
            <Icon name="fas fa-feather" /> {t('build.story_nudge')}
          </button>
        ) : null}

        {photos.length > 1 || (photos.length && build.canEdit()) ? (
          <section className="GarageSection">
            <h2 className="GarageSection-title">
              {t('build.gallery')} <span className="GarageSection-count">{photos.length}</span>
              {build.canEdit() ? (
                <Button
                  className="Button Button--link GarageSection-action"
                  icon="fas fa-images"
                  onclick={() => openBuildEditor({ build, tab: 'photos' })}
                >
                  {t('build.manage_photos')}
                </Button>
              ) : null}
            </h2>
            <PhotoGrid photos={photos} caption={build.title()} />
          </section>
        ) : !photos.length && build.canEdit() ? (
          <button type="button" className="GarageNudge" onclick={() => openBuildEditor({ build, tab: 'photos' })}>
            <Icon name="fas fa-camera" /> {t('build.photos_nudge')}
          </button>
        ) : null}

        <section className="GarageSection">
          <h2 className="GarageSection-title">
            {t('build.log')} <span className="GarageSection-count">{build.entryCount()}</span>
          </h2>
          {this.state.loaded ? <BuildLog build={build} state={this.state} /> : <LoadingIndicator display="block" />}
        </section>

        {build.published() ? (
          <section className="GarageSection" id="comments">
            <h2 className="GarageSection-title">
              {t('build.comments')} <span className="GarageSection-count">{this.state.comments.filter((c) => !c.entryId()).length}</span>
            </h2>
            {this.state.loaded ? <Comments build={build} state={this.state} /> : null}
          </section>
        ) : null}

        {this.more()}
      </div>
    );
  }

  /** The owner's other builds: a quiet link at the end. */
  more() {
    const user = this.build.user();
    if (!user || (user.attribute('garageBuildCount') || 0) < 2) return null;

    return (
      <Link className="GarageMore" href={app.route('user.garage', { username: user.slug() })}>
        <Avatar user={user} /> {t('build.more_from', { username: user.displayName() })} <Icon name="fas fa-arrow-right" />
      </Link>
    );
  }

  copyLink() {
    const url = app.forum.attribute('baseUrl') + buildUrl(this.build).replace(app.forum.attribute('basePath') || '', '');
    const done = () => app.alerts.show({ type: 'success' }, t('build.link_copied'));
    if (navigator.clipboard) navigator.clipboard.writeText(url).then(done, () => window.prompt('', url));
    else window.prompt('', url);
  }

  remove() {
    if (!confirm(extractText(t('build.delete_confirm', { title: this.build.title() })))) return;
    const user = this.build.user();
    this.build.delete().then(() => {
      m.route.set(user ? app.route('user.garage', { username: user.slug() }) : app.route('garage'));
    });
  }
}
