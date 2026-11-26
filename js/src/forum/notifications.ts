import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type User from 'flarum/common/models/User';
import type { GarageBuild } from '../common/models';
import { t, buildUrl } from './util';

interface TeamData {
  build?: number;
  slug?: string;
  title?: string;
  role?: string | null;
}

/** The build the team notification is about: its id, slug and title ride in the data. */
const teamHref = (data: TeamData) => (data.build ? buildUrl({ id: data.build, slug: data.slug || '' }) : app.route('garage'));

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

/** The reader was invited to a build's team. */
export class TeamInviteNotification extends Notification {
  icon() {
    return 'fas fa-user-plus';
  }

  href() {
    return teamHref(this.attrs.notification.content<TeamData>() || {});
  }

  content() {
    const notification = this.attrs.notification;
    return t('notifications.team_invite', {
      username: notification.fromUser() && (notification.fromUser() as User).displayName(),
      build: notification.content<TeamData>()?.title,
    });
  }

  excerpt() {
    return this.attrs.notification.content<TeamData>()?.role || null;
  }
}

/** Someone accepted the reader's invite to their build's team. */
export class TeamJoinedNotification extends Notification {
  icon() {
    return 'fas fa-people-group';
  }

  href() {
    return teamHref(this.attrs.notification.content<TeamData>() || {});
  }

  content() {
    const notification = this.attrs.notification;
    return t('notifications.team_joined', {
      username: notification.fromUser() && (notification.fromUser() as User).displayName(),
      build: notification.content<TeamData>()?.title,
    });
  }

  excerpt() {
    return this.attrs.notification.content<TeamData>()?.role || null;
  }
}
