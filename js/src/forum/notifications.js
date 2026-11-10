import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { t, buildUrl } from './util';

/** A build the reader follows has a new log entry. */
export class NewEntryNotification extends Notification {
  icon() {
    return 'fas fa-screwdriver-wrench';
  }

  href() {
    const build = this.attrs.notification.subject();
    const data = this.attrs.notification.content() || {};
    return build ? buildUrl(build) + (data.entry ? `?entry=${data.entry}` : '') : app.route('garage');
  }

  content() {
    const notification = this.attrs.notification;
    return t('notifications.new_entry', { username: notification.fromUser()?.displayName(), build: notification.subject()?.title?.() });
  }

  excerpt() {
    return (this.attrs.notification.content() || {}).title || null;
  }
}

/** Someone commented on the reader's build. */
export class CommentNotification extends Notification {
  icon() {
    return 'far fa-comment-dots';
  }

  href() {
    const build = this.attrs.notification.subject();
    const data = this.attrs.notification.content() || {};
    return build ? buildUrl(build) + (data.comment ? `?comment=${data.comment}` : '') : app.route('garage');
  }

  content() {
    const notification = this.attrs.notification;
    return t('notifications.comment', { username: notification.fromUser()?.displayName(), build: notification.subject()?.title?.() });
  }

  excerpt() {
    return (this.attrs.notification.content() || {}).excerpt || null;
  }
}
