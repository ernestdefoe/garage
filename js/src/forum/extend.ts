import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';
import { NewEntryNotification, CommentNotification, TeamInviteNotification, TeamJoinedNotification } from './notifications';
import { loadStyles } from './styles';

// Each page and the shared styles load side by side, so nothing shows unstyled.
const page =
  <T>(load: () => Promise<T>) =>
  () =>
    Promise.all([load(), loadStyles()]).then(([module]) => module);

export default [
  ...commonExtend,

  new Extend.Routes()
    .add(
      'garage',
      '/garage',
      page(() => import('./components/GaragePage'))
    )
    .add(
      'garage.build',
      '/garage/:id',
      page(() => import('./components/BuildPage'))
    )
    .add(
      'user.garage',
      '/u/:username/garage',
      page(() => import('./components/UserGaragePage'))
    ),

  new Extend.Notification()
    .add('garageNewEntry', NewEntryNotification)
    .add('garageComment', CommentNotification)
    .add('garageTeamInvite', TeamInviteNotification)
    .add('garageTeamJoined', TeamJoinedNotification),
];
