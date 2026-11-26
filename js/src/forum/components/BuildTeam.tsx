import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import username from 'flarum/common/helpers/username';
import extractText from 'flarum/common/utils/extractText';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import { openTeamManager } from './openers';
import type { GarageBuild, GarageMember } from '../../common/models';
import { t } from '../util';

interface TeamAttrs extends ComponentAttrs {
  build: GarageBuild;
}

/** The team's ids, written back to the build so its page follows a change. */
export function setTeam(build: GarageBuild, team: GarageMember[]) {
  build.pushData({ relationships: { team: { data: team.map((member) => ({ type: 'garage-members', id: member.id()! })) } } });
}

/** The build page's team, in the sidebar: the owner, then the members who accepted, each with a role. */
export default class BuildTeam extends Component<TeamAttrs> {
  view() {
    const build = this.attrs.build;
    const members = build.team();
    const manages = build.canEdit() && build.published();

    if (!members.length && !manages) return null;

    return (
      <section className="GarageTeam" aria-label={extractText(t('team.heading'))}>
        <h3 className="GarageSidebar-heading">
          <Icon name="fas fa-people-group" /> {t('team.heading')}
        </h3>
        <ul>
          {this.row(build.user(), t('team.owner'))}
          {members.map((member) => this.row(member.user(), member.role()))}
        </ul>
        {manages ? (
          <Button className="Button Button--link GarageTeam-manage" icon="fas fa-user-plus" onclick={() => openTeamManager({ build })}>
            {t(members.length ? 'team.manage' : 'team.invite_first')}
          </Button>
        ) : null}
      </section>
    );
  }

  row(user: User | null, role: Mithril.Children) {
    return (
      <li className="GarageTeam-member">
        <Link href={user ? app.route.user(user) : '#'} className="GarageTeam-link">
          <Avatar user={user} />
          <span className="GarageTeam-who">
            <span className="GarageTeam-name">{username(user)}</span>
            {role ? <span className="GarageTeam-role">{role}</span> : null}
          </span>
        </Link>
      </li>
    );
  }
}

/** The viewer's invite to the build's team, to accept or decline. */
export class TeamInvite extends Component<TeamAttrs> {
  // From the invite notification (?invite=1): on a phone the banner sits below the sidebar.
  oncreate(vnode: Mithril.VnodeDOM<TeamAttrs, this>) {
    super.oncreate(vnode);
    if (m.route.param('invite')) setTimeout(() => vnode.dom?.scrollIntoView?.({ block: 'center', behavior: 'smooth' }), 300);
  }

  view() {
    const build = this.attrs.build;
    const membership = build.viewerMembership();

    if (!membership || membership.accepted()) return null;

    return (
      <div className="GarageInvite">
        <Icon name="fas fa-user-plus" />
        <p>
          {t(membership.role() ? 'team.invited_as' : 'team.invited_plain', {
            username: build.user()?.displayName(),
            role: membership.role(),
          })}
        </p>
        <div className="GarageInvite-actions">
          <Button className="Button Button--primary" onclick={() => this.answer(membership, true)}>
            {t('team.accept')}
          </Button>
          <Button className="Button" onclick={() => this.answer(membership, false)}>
            {t('team.decline')}
          </Button>
        </div>
      </div>
    );
  }

  answer(membership: GarageMember, accept: boolean) {
    const build = this.attrs.build;
    const done = accept ? membership.save({ status: 'accepted' }) : membership.delete();

    // A declined invite is gone from the store, so viewerMembership() reads null again.
    done.then(() => {
      if (accept) setTeam(build, [...build.team(), membership]);
      m.redraw();
    });
  }
}

/** The build's Manage menu: the team window (the owner, a moderator), or Leave team (a member). */
export function teamControls(build: GarageBuild, items: ItemList<Mithril.Children>) {
  const membership = build.viewerMembership();

  if (build.canEdit() && build.published()) {
    items.add(
      'team',
      <Button icon="fas fa-people-group" onclick={() => openTeamManager({ build })}>
        {t('team.manage')}
      </Button>,
      90
    );
  }

  if (membership?.accepted()) {
    items.add(
      'leave',
      <Button icon="fas fa-right-from-bracket" onclick={() => leave(build, membership)}>
        {t('team.leave')}
      </Button>,
      50
    );
  }
}

function leave(build: GarageBuild, membership: GarageMember) {
  if (!confirm(extractText(t('team.leave_confirm')))) return;

  membership.delete().then(() => {
    setTeam(
      build,
      build.team().filter((member) => member !== membership)
    );
    m.redraw();
  });
}
