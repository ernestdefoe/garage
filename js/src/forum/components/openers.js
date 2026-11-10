import app from 'flarum/forum/app';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import { t, buildUrl } from '../util';
import { loadStyles } from '../styles';

/** The forms are a chunk of their own, fetched the first time one opens. */
export function openBuildEditor(attrs) {
  app.modal.show(() => Promise.all([import('./BuildEditModal'), loadStyles()]).then(([module]) => module), attrs);
}

export function openEntryEditor(attrs) {
  app.modal.show(() => Promise.all([import('./EntryModal'), loadStyles()]).then(([module]) => module), attrs);
}

/** A new discussion with the build's link on a line of its own, so the post shows its card. */
export function postAbout(build) {
  if (!app.session.user) return app.modal.show(() => import('flarum/forum/components/LogInModal'));
  const link = app.forum.attribute('baseUrl') + buildUrl(build).replace(app.forum.attribute('basePath') || '', '');

  return app.composer
    .load(() => import('flarum/forum/components/DiscussionComposer'), { user: app.session.user, originalContent: `${link}\n\n` })
    .then(() => {
      app.composer.fields.title = Stream(extractText(t('build.discuss_title', { title: build.title() })));
      app.composer.show();
    });
}
