import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import richText from '../text';
import { t } from '../util';

/**
 * The comments on a build, or on one of its log entries (attrs.entry). The
 * list is the page's (attrs.state.comments), so a comment shows wherever it
 * belongs as soon as it is posted.
 */
export default class Comments {
  oninit() {
    this.body = '';
    this.saving = false;
    this.editing = null;
  }

  oncreate(vnode) {
    // A notification about this comment: scroll to it once it is drawn.
    const wanted = m.route.param('comment');
    const node = wanted && vnode.dom.querySelector(`[data-comment="${CSS.escape(wanted)}"]`);
    if (node) setTimeout(() => node.scrollIntoView({ block: 'center', behavior: 'smooth' }), 300);
  }

  view(vnode) {
    const { build, entry, state } = vnode.attrs;
    const entryId = entry ? Number(entry.id()) : null;
    const comments = state.comments.filter((c) => (c.entryId() || null) === entryId);

    return (
      <div className={'GarageComments' + (entry ? ' GarageComments--entry' : '')}>
        {comments.length ? <ul className="GarageComments-list">{comments.map((comment) => this.comment(comment, state))}</ul> : null}
        {build.canComment() ? (
          this.form(vnode.attrs)
        ) : !app.session.user && build.published() ? (
          <p className="GarageComments-signin">
            <Button className="Button Button--link" onclick={() => app.modal.show(() => import('flarum/forum/components/LogInModal'))}>
              {t('comment.sign_in')}
            </Button>
          </p>
        ) : null}
      </div>
    );
  }

  comment(comment, state) {
    const user = comment.user();
    const editing = this.editing === comment;

    return (
      <li className="GarageComment" key={comment.id()} data-comment={comment.id()}>
        <Avatar user={user} className="GarageComment-avatar" />
        <div className="GarageComment-main">
          <div className="GarageComment-header">
            {user ? (
              <Link className="GarageComment-author" href={app.route.user(user)}>
                {user.displayName()}
              </Link>
            ) : null}
            <span className="GarageComment-time">{humanTime(comment.createdAt())}</span>
            {comment.editedAt() ? <span className="GarageComment-edited">{t('comment.edited')}</span> : null}
          </div>
          {editing ? (
            <div className="GarageComment-editor">
              <textarea
                className="FormControl"
                rows="3"
                value={this.draft}
                maxlength="2000"
                aria-label={extractText(t('comment.edit'))}
                oninput={(e) => (this.draft = e.target.value)}
              />
              <div className="GarageComment-actions">
                <Button className="Button Button--primary" loading={this.saving} disabled={!this.draft.trim()} onclick={() => this.saveEdit(comment)}>
                  {t('comment.save')}
                </Button>
                <Button className="Button" onclick={() => (this.editing = null)}>
                  {t('comment.cancel')}
                </Button>
              </div>
            </div>
          ) : (
            <div className="GarageComment-body">{richText(comment.body())}</div>
          )}
          {!editing && (comment.canEdit() || comment.canDelete()) ? (
            <div className="GarageComment-tools">
              {comment.canEdit() ? (
                <Button
                  className="Button Button--link"
                  onclick={() => {
                    this.editing = comment;
                    this.draft = comment.body();
                  }}
                >
                  {t('comment.edit')}
                </Button>
              ) : null}
              {comment.canDelete() ? (
                <Button className="Button Button--link" onclick={() => this.remove(comment, state)}>
                  {t('comment.delete')}
                </Button>
              ) : null}
            </div>
          ) : null}
        </div>
      </li>
    );
  }

  form({ build, entry, state }) {
    return (
      <form
        className="GarageComments-form"
        onsubmit={(e) => {
          e.preventDefault();
          this.post(build, entry, state);
        }}
      >
        <Avatar user={app.session.user} className="GarageComment-avatar" />
        <div className="GarageComments-field">
          <textarea
            className="FormControl"
            rows={this.body ? 3 : 1}
            maxlength="2000"
            value={this.body}
            placeholder={extractText(t(entry ? 'comment.placeholder_entry' : 'comment.placeholder'))}
            aria-label={extractText(t('comment.label'))}
            oninput={(e) => (this.body = e.target.value)}
            onkeydown={(e) => {
              // Ctrl/Cmd + Enter posts. A block, not `cond && …`: a handler returning false cancels every key.
              if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                this.post(build, entry, state);
              }
            }}
          />
          {this.body ? (
            <Button type="submit" className="Button Button--primary" loading={this.saving} disabled={!this.body.trim()}>
              {t('comment.post')}
            </Button>
          ) : null}
        </div>
      </form>
    );
  }

  post(build, entry, state) {
    if (!this.body.trim() || this.saving) return;
    this.saving = true;

    app.store
      .createRecord('garage-comments')
      .save({
        body: this.body,
        entryId: entry ? Number(entry.id()) : null,
        relationships: { build },
      })
      .then((comment) => {
        state.comments.push(comment);
        build.pushAttributes({ commentCount: (build.commentCount() || 0) + 1 });
        if (entry) entry.pushAttributes({ commentCount: (entry.commentCount() || 0) + 1 });
        this.body = '';
      })
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }

  saveEdit(comment) {
    this.saving = true;
    comment
      .save({ body: this.draft })
      .then(() => (this.editing = null))
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }

  remove(comment, state) {
    if (!confirm(extractText(t('comment.delete_confirm')))) return;
    comment.delete().then(() => {
      state.comments = state.comments.filter((c) => c !== comment);
      m.redraw();
    });
  }
}
