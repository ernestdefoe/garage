import app from 'flarum/forum/app';
import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import username from 'flarum/common/helpers/username';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type RequestError from 'flarum/common/utils/RequestError';
import useStyles from '../styles';
import { setTeam } from './BuildTeam';
import type { GarageBuild, GarageMember } from '../../common/models';
import { t } from '../util';

export interface TeamModalAttrs extends IFormModalAttrs {
  build: GarageBuild;
}

const MAX_ROLE = 60;

/**
 * The owner's (or a moderator's) team window: who is on the build's team and
 * who is invited, their roles, and a form to invite someone else by name.
 */
export default class TeamModal extends FormModal<TeamModalAttrs> {
  members!: GarageMember[] | null;
  query!: Stream<string>;
  role!: Stream<string>;
  results!: User[];
  picked!: User | null;
  searchTimer!: ReturnType<typeof setTimeout> | undefined;

  oninit(vnode: Mithril.Vnode<TeamModalAttrs, this>) {
    super.oninit(vnode);
    useStyles();
    this.members = null;
    this.query = Stream('');
    this.role = Stream('');
    this.results = [];
    this.picked = null;

    app.store.find<GarageMember[]>('garage-members', { filter: { build: this.attrs.build.id()! }, include: 'user' }).then((members) => {
      this.members = members;
      m.redraw();
    });
  }

  className() {
    return 'GarageTeamModal Modal--small';
  }

  title() {
    return t('team.manage_title');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.inviteForm()}
          {this.members ? this.list() : <LoadingIndicator display="block" />}
        </div>
      </div>
    );
  }

  inviteForm() {
    return (
      <fieldset className="GarageTeamModal-invite">
        <legend>{t('team.invite_heading')}</legend>
        <div className="Form-group">
          <label for="garage-team-user">{t('team.member_label')}</label>
          <input
            id="garage-team-user"
            className="FormControl"
            autocomplete="off"
            autocapitalize="none"
            autocorrect="off"
            spellcheck={false}
            placeholder={extractText(t('team.member_placeholder'))}
            value={this.picked ? this.picked.username() : this.query()}
            oninput={(e: InputEvent) => this.search((e.target as HTMLInputElement).value)}
          />
          {this.results.length && !this.picked ? (
            <ul className="GarageTeamModal-results" role="listbox">
              {this.results.map((user) => (
                <li role="option">
                  {/* On pointerdown: on a phone the tap that closes the keyboard would otherwise be lost. */}
                  <button
                    type="button"
                    onpointerdown={(e: PointerEvent) => {
                      e.preventDefault();
                      this.pick(user);
                    }}
                    onclick={() => this.pick(user)}
                  >
                    <Avatar user={user} /> {username(user)}
                  </button>
                </li>
              ))}
            </ul>
          ) : null}
        </div>
        <div className="Form-group">
          <label for="garage-team-role">{t('team.role_label')}</label>
          <input
            id="garage-team-role"
            className="FormControl"
            maxlength={MAX_ROLE}
            placeholder={extractText(t('team.role_placeholder'))}
            bidi={this.role}
            onfocus={() => (this.results = [])}
          />
        </div>
        <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={!this.picked && !this.query().trim()}>
          {t('team.invite')}
        </Button>
      </fieldset>
    );
  }

  list() {
    const members = this.members || [];

    if (!members.length) return <p className="GarageTeamModal-empty">{t('team.empty')}</p>;

    return (
      <ul className="GarageTeamModal-list">
        {members.map((member) => (
          <li key={member.id()} className="GarageTeamModal-member">
            <Avatar user={member.user()} />
            <div className="GarageTeamModal-who">
              <span className="GarageTeamModal-name">
                {username(member.user())}
                {member.accepted() ? null : <span className="GarageTeamModal-pending">{t('team.invited')}</span>}
              </span>
              <input
                className="FormControl"
                aria-label={extractText(t('team.role_for', { username: member.user()?.displayName() }))}
                maxlength={MAX_ROLE}
                placeholder={extractText(t('team.role_placeholder'))}
                value={member.role() || ''}
                disabled={!member.canEdit()}
                onchange={(e: Event) => this.saveRole(member, (e.target as HTMLInputElement).value)}
              />
            </div>
            {member.canDelete() ? (
              <Button
                className="Button Button--icon Button--link"
                icon="fas fa-times"
                aria-label={extractText(t(member.accepted() ? 'team.remove' : 'team.withdraw', { username: member.user()?.displayName() }))}
                title={extractText(t(member.accepted() ? 'team.remove' : 'team.withdraw', { username: member.user()?.displayName() }))}
                onclick={() => this.removeMember(member)}
              />
            ) : null}
          </li>
        ))}
      </ul>
    );
  }

  /** Names as they're typed, a quarter second after the last key (needs the forum's user search). */
  search(value: string) {
    this.query(value);
    this.picked = null;
    this.alertAttrs = null;
    clearTimeout(this.searchTimer);

    if (!value.trim() || !app.forum.attribute('canSearchUsers')) {
      this.results = [];
      return;
    }

    this.searchTimer = setTimeout(() => {
      app.store.find<User[]>('users', { filter: { q: value.trim() }, page: { limit: 5 } }).then((users) => {
        if (this.query() !== value) return;
        this.results = users.filter((user) => user !== this.attrs.build.user());
        m.redraw();
      });
    }, 250);
  }

  pick(user: User) {
    this.picked = user;
    this.results = [];
  }

  /** The picked member, one of the results whose name was typed in full, or a lookup by that name. */
  invitee(): Promise<User> {
    const typed = this.query().trim().toLowerCase();
    const match = this.results.find((user) => user.username().toLowerCase() === typed);
    if (this.picked || match) return Promise.resolve((this.picked || match)!);

    // A name nobody has gets our own message, not Flarum's "resource not found".
    return app.store.find<User>('users', this.query().trim(), { bySlug: true }, { errorHandler: (error: RequestError) => Promise.reject(error) });
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();
    this.loading = true;
    // What the fields show, not the last input event: iOS can still be holding the last
    // typed letter (autocorrect) when Send is tapped.
    const field = (id: string) => (this.element.querySelector<HTMLInputElement>(`#${id}`)?.value ?? '').trim();
    this.role(field('garage-team-role'));
    if (!this.picked) this.query(field('garage-team-user'));

    this.invitee()
      .then((user) =>
        app.store.createRecord<GarageMember>('garage-members').save({
          role: this.role().trim() || null,
          relationships: { user, build: this.attrs.build },
        })
      )
      .then((member) => {
        this.members = [...(this.members || []), member];
        this.query('');
        this.role('');
        this.picked = null;
        this.alertAttrs = null;
      })
      .catch((error: RequestError) => {
        if (error.status === 404) {
          this.alertAttrs = { type: 'error', content: t('team.no_such_member', { username: this.query().trim() }) };
        } else {
          this.onerror(error);
        }
      })
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  saveRole(member: GarageMember, role: string) {
    member.save({ role: role.trim() || null }).then(() => m.redraw());
  }

  removeMember(member: GarageMember) {
    member.delete().then(() => {
      this.members = (this.members || []).filter((other) => other !== member);
      // The page's team list follows: a removed member leaves the build's relationship too.
      setTeam(
        this.attrs.build,
        this.attrs.build.team().filter((other) => other !== member)
      );
      m.redraw();
    });
  }
}
