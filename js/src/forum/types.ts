import type { GarageComment, GarageEntry } from '../common/models';

/** A build page's log and comments, shared by BuildLog, Comments and the update form. */
export interface LogState {
  entries: GarageEntry[];
  comments: GarageComment[];
  moreEntries: boolean;
  loaded?: boolean;
}
