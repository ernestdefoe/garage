import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';

export interface GaragePhoto {
  url: string;
  small: string;
  w: number | null;
  h: number | null;
}

/** A build's card as the server sends it with posts and users (garageEmbeds, garageSignature). */
export interface GarageCardData {
  id: number;
  slug: string;
  title: string;
  tagline: string | null;
  stage: string;
  cover: string | null;
  likeCount: number;
  entryCount: number;
  category: { name: string; color: string | null; icon: string | null } | null;
  user: { displayName: string; slug: string; avatarUrl: string | null } | null;
}

export interface GarageField {
  key: string;
  label: string;
  type: 'text' | 'number' | 'select' | 'link';
  unit?: string;
  options?: string[];
  card?: boolean;
  filter?: boolean;
}

export class GarageCategory extends Model {
  name() {
    return Model.attribute<string>('name').call(this);
  }
  slug() {
    return Model.attribute<string>('slug').call(this);
  }
  description() {
    return Model.attribute<string | null>('description').call(this);
  }
  icon() {
    return Model.attribute<string | null>('icon').call(this);
  }
  color() {
    return Model.attribute<string | null>('color').call(this);
  }
  fields() {
    return Model.attribute<GarageField[]>('fields').call(this);
  }
  position() {
    return Model.attribute<number>('position').call(this);
  }
  buildCount() {
    return Model.attribute<number>('buildCount').call(this);
  }
}

export class GarageBuild extends Model {
  title() {
    return Model.attribute<string>('title').call(this);
  }
  slug() {
    return Model.attribute<string>('slug').call(this);
  }
  tagline() {
    return Model.attribute<string | null>('tagline').call(this);
  }
  story() {
    return Model.attribute<string | null>('story').call(this);
  }
  storySource() {
    return Model.attribute<string | null>('storySource').call(this);
  }
  storyHtml() {
    return Model.attribute<string | null>('storyHtml').call(this);
  }
  stage() {
    return Model.attribute<string>('stage').call(this);
  }
  specs() {
    return Model.attribute<Record<string, unknown>>('specs').call(this);
  }
  photos() {
    return Model.attribute<GaragePhoto[] | null>('photos').call(this);
  }
  entryCount() {
    return Model.attribute<number>('entryCount').call(this);
  }
  likeCount() {
    return Model.attribute<number>('likeCount').call(this);
  }
  commentCount() {
    return Model.attribute<number>('commentCount').call(this);
  }
  followerCount() {
    return Model.attribute<number>('followerCount').call(this);
  }
  spent() {
    return Model.attribute<number | null>('spent').call(this);
  }
  published() {
    return Model.attribute<boolean>('published').call(this);
  }
  featured() {
    return Model.attribute<boolean>('featured').call(this);
  }
  publishedAt() {
    return Model.attribute<Date | null, string | null>('publishedAt', Model.transformDate).call(this);
  }
  finishedAt() {
    return Model.attribute<Date | null, string | null>('finishedAt', Model.transformDate).call(this);
  }
  lastActivityAt() {
    return Model.attribute<Date | null, string | null>('lastActivityAt', Model.transformDate).call(this);
  }
  createdAt() {
    return Model.attribute<Date | null, string | null>('createdAt', Model.transformDate).call(this);
  }
  liked() {
    return Model.attribute<boolean>('liked').call(this);
  }
  followed() {
    return Model.attribute<boolean>('followed').call(this);
  }
  inSignature() {
    return Model.attribute<boolean>('inSignature').call(this);
  }
  canEdit() {
    return Model.attribute<boolean>('canEdit').call(this);
  }
  canDelete() {
    return Model.attribute<boolean>('canDelete').call(this);
  }
  canFeature() {
    return Model.attribute<boolean>('canFeature').call(this);
  }
  canLike() {
    return Model.attribute<boolean>('canLike').call(this);
  }
  canFollow() {
    return Model.attribute<boolean>('canFollow').call(this);
  }
  canComment() {
    return Model.attribute<boolean>('canComment').call(this);
  }
  user(): User | null {
    return Model.hasOne<User>('user').call(this) || null;
  }
  category(): GarageCategory | null {
    return Model.hasOne<GarageCategory>('category').call(this) || null;
  }
  lastEntry(): GarageEntry | null {
    return Model.hasOne<GarageEntry>('lastEntry').call(this) || null;
  }
  cover(): GaragePhoto | null {
    return (this.photos() || [])[0] || null;
  }
}

export class GarageEntry extends Model {
  title() {
    return Model.attribute<string>('title').call(this);
  }
  body() {
    return Model.attribute<string | null>('body').call(this);
  }
  bodySource() {
    return Model.attribute<string | null>('bodySource').call(this);
  }
  bodyHtml() {
    return Model.attribute<string | null>('bodyHtml').call(this);
  }
  cost() {
    return Model.attribute<number | null>('cost').call(this);
  }
  happenedOn() {
    return Model.attribute<string | null>('happenedOn').call(this);
  }
  photos() {
    return Model.attribute<GaragePhoto[] | null>('photos').call(this);
  }
  commentCount() {
    return Model.attribute<number>('commentCount').call(this);
  }
  createdAt() {
    return Model.attribute<Date | null, string | null>('createdAt', Model.transformDate).call(this);
  }
  canEdit() {
    return Model.attribute<boolean>('canEdit').call(this);
  }
  build(): GarageBuild | null {
    return Model.hasOne<GarageBuild>('build').call(this) || null;
  }
}

export class GarageComment extends Model {
  body() {
    return Model.attribute<string | null>('body').call(this);
  }
  entryId() {
    return Model.attribute<number | null>('entryId').call(this);
  }
  createdAt() {
    return Model.attribute<Date | null, string | null>('createdAt', Model.transformDate).call(this);
  }
  editedAt() {
    return Model.attribute<Date | null, string | null>('editedAt', Model.transformDate).call(this);
  }
  canEdit() {
    return Model.attribute<boolean>('canEdit').call(this);
  }
  canDelete() {
    return Model.attribute<boolean>('canDelete').call(this);
  }
  user(): User | null {
    return Model.hasOne<User>('user').call(this) || null;
  }
}
