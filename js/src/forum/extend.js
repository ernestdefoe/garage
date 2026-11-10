import Extend from 'flarum/common/extenders';
import { NewEntryNotification, CommentNotification } from './notifications';

export default [new Extend.Notification().add('garageNewEntry', NewEntryNotification).add('garageComment', CommentNotification)];
