import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import CategoriesEditor from './CategoriesEditor';

const t = (key) => app.translator.trans(`ernestdefoe-garage.admin.${key}`);
const setting = (key) => `ernestdefoe-garage.${key}`;

export default [
  new Extend.Admin()
    .customSetting(() => <CategoriesEditor />, 200)
    .setting(() => ({ setting: setting('nav'), type: 'boolean', label: t('settings.nav') }), 100)
    .setting(
      () =>
        'fof-upload' in (flarum.extensions || {})
          ? { setting: setting('use_fof_upload'), type: 'boolean', label: t('settings.use_fof_upload'), help: t('settings.use_fof_upload_help') }
          : null,
      90
    )
    .setting(
      () => ({
        setting: setting('max_upload_kb'),
        type: 'number',
        min: 256,
        label: t('settings.max_upload_kb'),
        help: t('settings.max_upload_kb_help'),
      }),
      80
    )
    .setting(
      () => ({ setting: setting('currency'), type: 'text', label: t('settings.currency'), help: t('settings.currency_help'), placeholder: '$' }),
      70
    )
    .permission(
      () => ({ permission: 'ernestdefoe-garage.view', icon: 'fas fa-warehouse', label: t('permissions.view'), allowGuest: true }),
      'view',
      60
    )
    .permission(() => ({ permission: 'ernestdefoe-garage.create', icon: 'fas fa-screwdriver-wrench', label: t('permissions.create') }), 'start', 60)
    .permission(() => ({ permission: 'ernestdefoe-garage.like', icon: 'far fa-heart', label: t('permissions.like') }), 'reply', 60)
    .permission(() => ({ permission: 'ernestdefoe-garage.comment', icon: 'far fa-comment-dots', label: t('permissions.comment') }), 'reply', 59)
    .permission(() => ({ permission: 'ernestdefoe-garage.moderate', icon: 'fas fa-warehouse', label: t('permissions.moderate') }), 'moderate', 60),
];
