import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import BuildCard from './BuildCard';
import { openBuildEditor } from './openers';
import useStyles from '../styles';
import { t, money } from '../util';

/** Profile → Garage: the member's builds (their drafts too, on their own profile). */
export default class UserGaragePage extends UserPage {
  oninit(vnode) {
    super.oninit(vnode);
    useStyles();
    this.builds = null;
    this.loadUser(m.route.param('username'));
  }

  show(user) {
    super.show(user);
    if (this.shownFor === user.id()) return;
    this.shownFor = user.id();

    app.store.find('garage-builds', { filter: { user: user.id() }, sort: '-lastActivityAt', page: { limit: 48, total: 0 } }).then((builds) => {
      this.builds = builds;
      m.redraw();
    });
  }

  content() {
    if (!this.builds) return <LoadingIndicator display="block" />;

    const own = app.session.user === this.user;
    const canAdd = own && app.forum.attribute('garageCanCreate');
    const published = this.builds.filter((b) => b.published());
    const sum = (fn) => published.reduce((n, b) => n + (Number(fn(b)) || 0), 0);

    return (
      <div className="GarageUserPage">
        {this.builds.length ? (
          <div className="GarageUserPage-header">
            <ul className="GarageUserPage-stats">
              <li>
                <strong>{published.length}</strong> {t('profile.builds', { count: published.length })}
              </li>
              <li>
                <strong>{sum((b) => b.entryCount())}</strong> {t('profile.updates', { count: sum((b) => b.entryCount()) })}
              </li>
              <li>
                <strong>{sum((b) => b.likeCount())}</strong> {t('profile.likes', { count: sum((b) => b.likeCount()) })}
              </li>
              {sum((b) => b.spent()) ? (
                <li>
                  <strong>{money(sum((b) => b.spent()))}</strong> {t('profile.spent')}
                </li>
              ) : null}
            </ul>
            {canAdd ? (
              <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => openBuildEditor({})}>
                {t('page.add')}
              </Button>
            ) : null}
          </div>
        ) : null}
        {this.builds.length ? (
          <div className="GarageGrid GarageGrid--profile">
            {this.builds.map((build) => (
              <BuildCard build={build} />
            ))}
          </div>
        ) : (
          <div className="GarageEmpty">
            <Icon name="fas fa-warehouse" />
            <p>{own ? t('profile.empty_own') : t('profile.empty', { username: this.user.displayName() })}</p>
            {canAdd ? (
              <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => openBuildEditor({})}>
                {t('page.add')}
              </Button>
            ) : null}
          </div>
        )}
      </div>
    );
  }
}
