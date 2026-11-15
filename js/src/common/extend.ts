import Extend from 'flarum/common/extenders';
import { GarageBuild, GarageCategory, GarageComment, GarageEntry } from './models';

/** Garage's models, shared by the forum and the admin. */
export default [
  new Extend.Store()
    .add('garage-categories', GarageCategory)
    .add('garage-builds', GarageBuild)
    .add('garage-entries', GarageEntry)
    .add('garage-comments', GarageComment),
];
