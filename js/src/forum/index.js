import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import ModalManagerState from 'flarum/common/states/ModalManagerState';
import LinkButton from 'flarum/common/components/LinkButton';
import UserPage from 'flarum/forum/components/UserPage';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import CommentPost from 'flarum/forum/components/CommentPost';
import { registerModels } from '../common/models';
import EmbedCard from './components/EmbedCard';
import { t } from './util';
import { loadStyles } from './styles';

/*
 * forum.js is loaded on every page, so it only wires things up: the routes,
 * the sidebar link, the profile tab, the notifications, a build's card in
 * posts and under signatures. The pages and the forms are chunks fetched when
 * opened.
 */

app.initializers.add('ernestdefoe-garage', () => {
  registerModels(app);

  // A window opened over a build or update form (FoF Upload's file-name prompt,
  // say) stacks on top instead of replacing it, so what was written isn't lost.
  override(ModalManagerState.prototype, 'show', function (original, componentClass, attrs, stack = false) {
    return original(componentClass, attrs, stack || !!this.modal?.componentClass?.keepsOthersOnTop);
  });

  // Each page and the shared styles load side by side.
  const page = (load) => () => Promise.all([load(), loadStyles()]).then(([module]) => module);
  app.routes['garage'] = { path: '/garage', component: page(() => import('./components/GaragePage')) };
  app.routes['garage.build'] = { path: '/garage/:id', component: page(() => import('./components/BuildPage')) };
  app.routes['user.garage'] = { path: '/u/:username/garage', component: page(() => import('./components/UserGaragePage')) };

  extend(IndexSidebar.prototype, 'navItems', function (items) {
    if (!app.forum.attribute('garageCanView') || !app.forum.attribute('garageNav')) return;
    items.add(
      'garage',
      <LinkButton href={app.route('garage')} icon="fas fa-warehouse">
        {t('nav')}
      </LinkButton>,
      84
    );
  });

  extend(UserPage.prototype, 'navItems', function (items) {
    if (!this.user || !app.forum.attribute('garageCanView')) return;
    const count = this.user.attribute('garageBuildCount') || 0;
    // A member with no builds gets no tab, except on their own profile (where they add one).
    if (!count && app.session.user !== this.user) return;
    items.add(
      'garage',
      <LinkButton href={app.route('user.garage', { username: this.user.slug() })} icon="fas fa-warehouse">
        {t('profile.nav')} <span className="Button-badge">{count}</span>
      </LinkButton>,
      85
    );
  });

  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', (items) => {
    items.add('garageNewEntry', { name: 'garageNewEntry', icon: 'fas fa-screwdriver-wrench', label: t('notifications.new_entry_setting') });
    items.add('garageComment', { name: 'garageComment', icon: 'far fa-comment-dots', label: t('notifications.comment_setting') });
  });

  // A build linked on a line of its own becomes its card.
  extend(CommentPost.prototype, 'oncreate', embedBuilds);
  extend(CommentPost.prototype, 'onupdate', embedBuilds);

  // With FoF Signature: the build the member chose, under their posts.
  extend(CommentPost.prototype, 'content', function (content) {
    const post = this.attrs.post;
    const card = post.user?.()?.attribute?.('garageSignature');
    if (!card || post.isHidden()) return;
    content.push(
      <div className="GarageSignature">
        <EmbedCard card={card} compact />
      </div>
    );
  });
});

/**
 * Swaps a paragraph's lone link to a build for the build's card. The card is
 * drawn INSIDE the paragraph (which stays where it is): Mithril tracks the
 * post's top-level nodes, and replacing one would break its next redraw.
 */
function embedBuilds() {
  const cards = this.attrs.post.attribute('garageEmbeds');
  const body = cards && this.element?.querySelector('.Post-body');
  if (!body) return;

  const base = app.forum.attribute('baseUrl') + '/garage/';
  body.querySelectorAll('a[href]:not([data-garage])').forEach((link) => {
    if (!link.href.startsWith(base)) return;
    link.dataset.garage = '1';
    const match = link.href.slice(base.length).match(/^(\d+)/);
    const card = match && cards.find((c) => String(c.id) === match[1]);
    const paragraph = link.parentElement;
    const alone = paragraph?.tagName === 'P' && paragraph.textContent.trim() === link.textContent.trim();
    if (!card || !alone) return;

    const holder = document.createElement('span');
    holder.className = 'GarageEmbed-holder';
    link.replaceWith(holder);
    m.render(holder, <EmbedCard card={card} />);
  });
}
