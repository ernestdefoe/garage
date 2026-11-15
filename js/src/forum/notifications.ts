import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type User from 'flarum/common/models/User';
import type { GarageBuild } from '../common/models';
import { t, buildUrl } from './util';

interface EntryData {
  entry?: number;
  title?: string;
}

interface CommentData {
  comment?: number;
  excerpt?: string;
}

/** A build the reader follows has a new log entry. */
export class NewEntryNotification extends Notification {
  icon() {
    return 'fas fa-screwdriver-wrench';
  }

  href() {
    const build = this.attrs.notification.subject() as GarageBuild | false;
    const data = this.attrs.notification.content<EntryData>() || {};
    return build ? buildUrl(build) + (data.entry ? `?entry=${data.entry}` : '') : app.route('garage');
  }

  content() {
    const notification = this.attrs.notification;
    const build = notification.subject() as GarageBuild | false;
    return t('notifications.new_entry', {
      username: notification.fromUser() && (notification.fromUser() as User).displayName(),
      build: build && build.title(),
    });
  }

  excerpt() {
    return this.attrs.notification.content<EntryData>()?.title || null;
  }
}

/** Someone commented on the reader's build. */
export class CommentNotification extends Notification {
  icon() {
    return 'far fa-comment-dots';
  }

  href() {
    const build = this.attrs.notification.subject() as GarageBuild | false;
    const data = this.attrs.notification.content<CommentData>() || {};
    return build ? buildUrl(build) + (data.comment ? `?comment=${data.comment}` : '') : app.route('garage');
  }

  content() {
    const notification = this.attrs.notification;
    const build = notification.subject() as GarageBuild | false;
    return t('notifications.comment', {
      username: notification.fromUser() && (notification.fromUser() as User).displayName(),
      build: build && build.title(),
    });
  }

  excerpt() {
    return this.attrs.notification.content<CommentData>()?.excerpt || null;
  }
}
