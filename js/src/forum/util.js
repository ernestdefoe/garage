import app from 'flarum/forum/app';

export const t = (key, params = {}) => app.translator.trans(`ernestdefoe-garage.forum.${key}`, params);

/** A build's page: from a model or from a card's plain data. */
export function buildUrl(build) {
  const id = typeof build.id === 'function' ? build.id() : build.id;
  const slug = typeof build.slug === 'function' ? build.slug() : build.slug;
  return app.route('garage.build', { id: slug ? `${id}-${slug}` : id });
}

/** An amount with the admin's currency sign: "$1,250". */
export function money(amount) {
  if (amount === null || amount === undefined) return '';
  const sign = app.forum.attribute('garageCurrency') ?? '$';
  const cents = Number(amount) % 1 ? 2 : 0;
  const number = Number(amount).toLocaleString(undefined, { minimumFractionDigits: cents, maximumFractionDigits: cents });
  return sign + number;
}

export const STAGES = ['planning', 'in_progress', 'complete', 'for_sale', 'retired'];

export const STAGE_ICONS = {
  planning: 'fas fa-compass-drafting',
  in_progress: 'fas fa-screwdriver-wrench',
  complete: 'fas fa-flag-checkered',
  for_sale: 'fas fa-tag',
  retired: 'fas fa-box-archive',
};

/** A category's colour as a CSS value, or the forum's primary colour. */
export function categoryColor(color) {
  return /^#[0-9a-f]{3,8}$/i.test(color || '') ? color : 'var(--primary-color)';
}
