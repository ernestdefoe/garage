import Model from 'flarum/common/Model';

export class GarageCategory extends Model {}
Object.assign(GarageCategory.prototype, {
  name: Model.attribute('name'),
  slug: Model.attribute('slug'),
  description: Model.attribute('description'),
  icon: Model.attribute('icon'),
  color: Model.attribute('color'),
  fields: Model.attribute('fields'),
  position: Model.attribute('position'),
  buildCount: Model.attribute('buildCount'),
});

export class GarageBuild extends Model {}
Object.assign(GarageBuild.prototype, {
  title: Model.attribute('title'),
  slug: Model.attribute('slug'),
  tagline: Model.attribute('tagline'),
  story: Model.attribute('story'),
  stage: Model.attribute('stage'),
  specs: Model.attribute('specs'),
  photos: Model.attribute('photos'),
  entryCount: Model.attribute('entryCount'),
  likeCount: Model.attribute('likeCount'),
  commentCount: Model.attribute('commentCount'),
  followerCount: Model.attribute('followerCount'),
  spent: Model.attribute('spent'),
  published: Model.attribute('published'),
  featured: Model.attribute('featured'),
  publishedAt: Model.attribute('publishedAt', Model.transformDate),
  lastActivityAt: Model.attribute('lastActivityAt', Model.transformDate),
  createdAt: Model.attribute('createdAt', Model.transformDate),
  liked: Model.attribute('liked'),
  followed: Model.attribute('followed'),
  inSignature: Model.attribute('inSignature'),
  canEdit: Model.attribute('canEdit'),
  canDelete: Model.attribute('canDelete'),
  canFeature: Model.attribute('canFeature'),
  canLike: Model.attribute('canLike'),
  canFollow: Model.attribute('canFollow'),
  canComment: Model.attribute('canComment'),
  user: Model.hasOne('user'),
  category: Model.hasOne('category'),
  lastEntry: Model.hasOne('lastEntry'),
});

GarageBuild.prototype.cover = function () {
  return (this.photos() || [])[0] || null;
};

export class GarageEntry extends Model {}
Object.assign(GarageEntry.prototype, {
  title: Model.attribute('title'),
  body: Model.attribute('body'),
  cost: Model.attribute('cost'),
  happenedOn: Model.attribute('happenedOn'),
  photos: Model.attribute('photos'),
  commentCount: Model.attribute('commentCount'),
  createdAt: Model.attribute('createdAt', Model.transformDate),
  canEdit: Model.attribute('canEdit'),
  build: Model.hasOne('build'),
});

export class GarageComment extends Model {}
Object.assign(GarageComment.prototype, {
  body: Model.attribute('body'),
  entryId: Model.attribute('entryId'),
  createdAt: Model.attribute('createdAt', Model.transformDate),
  editedAt: Model.attribute('editedAt', Model.transformDate),
  canEdit: Model.attribute('canEdit'),
  canDelete: Model.attribute('canDelete'),
  user: Model.hasOne('user'),
});

export function registerModels(app) {
  app.store.models['garage-categories'] = GarageCategory;
  app.store.models['garage-builds'] = GarageBuild;
  app.store.models['garage-entries'] = GarageEntry;
  app.store.models['garage-comments'] = GarageComment;
}
