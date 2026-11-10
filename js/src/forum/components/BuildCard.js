import app from 'flarum/forum/app';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import Avatar from 'flarum/common/components/Avatar';
import humanTime from 'flarum/common/helpers/humanTime';
import { t, buildUrl, money, categoryColor, STAGE_ICONS } from '../util';

/** The fields of a build's spec sheet that its category shows on cards, as "value unit". */
export function cardSpecs(build) {
  const fields = build.category()?.fields() || [];
  const specs = build.specs() || {};
  return fields
    .filter((field) => field.card && specs[field.key] !== undefined && field.type !== 'link')
    .slice(0, 3)
    .map((field) => ({ label: field.label, value: formatSpec(field, specs[field.key]) }));
}

export function formatSpec(field, value) {
  if (field.type === 'number') {
    // Grouped only from five digits: a year (1974) or a model number stays as typed.
    const number = Math.abs(Number(value)) >= 10000 ? Number(value).toLocaleString() : String(value);
    return field.unit ? `${number} ${field.unit}` : number;
  }
  return String(value);
}

/** A build in the grid: its cover, what it is, whose it is and when it last moved. */
export default function BuildCard({ attrs }) {
  return {
    view() {
      const build = attrs.build;
      const cover = build.cover();
      const category = build.category();
      const user = build.user();
      const latest = build.lastEntry();
      const specs = cardSpecs(build);

      return (
        <article className={'GarageCard' + (attrs.large ? ' GarageCard--large' : '')} style={{ '--garage-accent': categoryColor(category?.color()) }}>
          <Link href={buildUrl(build)} className="GarageCard-cover" aria-label={build.title()}>
            {cover ? (
              <img src={attrs.large ? cover.url : cover.small} alt="" loading="lazy" decoding="async" />
            ) : (
              <span className="GarageCard-noCover">
                <Icon name={category?.icon() || 'fas fa-warehouse'} />
              </span>
            )}
            <span className={`GarageStage GarageStage--${build.stage()}`}>
              <Icon name={STAGE_ICONS[build.stage()]} /> {t(`stage.${build.stage()}`)}
            </span>
            {!build.published() ? <span className="GarageCard-draft">{t('card.draft')}</span> : null}
            {build.featured() ? (
              <span className="GarageCard-featured" title={t('page.featured')}>
                <Icon name="fas fa-star" />
              </span>
            ) : null}
          </Link>
          <div className="GarageCard-body">
            {category ? (
              <Link className="GarageCard-category" href={app.route('garage', { category: category.slug() })}>
                <Icon name={category.icon() || 'fas fa-warehouse'} /> {category.name()}
              </Link>
            ) : null}
            <h3 className="GarageCard-title">
              <Link href={buildUrl(build)}>{build.title()}</Link>
            </h3>
            {build.tagline() ? <p className="GarageCard-tagline">{build.tagline()}</p> : null}
            {specs.length ? (
              <ul className="GarageCard-specs">
                {specs.map((spec) => (
                  <li title={spec.label}>{spec.value}</li>
                ))}
              </ul>
            ) : null}
            {latest ? (
              <p className="GarageCard-latest">
                <Icon name="fas fa-clock-rotate-left" /> {t('card.latest', { title: latest.title() })}
              </p>
            ) : null}
            <div className="GarageCard-footer">
              {user ? (
                <Link className="GarageCard-owner" href={app.route.user(user)}>
                  <Avatar user={user} /> <span>{user.displayName()}</span>
                </Link>
              ) : null}
              <span className="GarageCard-stats">
                {build.spent() ? <span title={t('build.stats.spent')}>{money(build.spent())}</span> : null}
                <span title={t('build.stats.likes')}>
                  <Icon name={build.liked() ? 'fas fa-heart' : 'far fa-heart'} /> {build.likeCount()}
                </span>
                <span title={t('build.stats.updates')}>
                  <Icon name="fas fa-list-check" /> {build.entryCount()}
                </span>
              </span>
            </div>
            {build.lastActivityAt() ? <div className="GarageCard-time">{t('card.updated', { ago: humanTime(build.lastActivityAt()) })}</div> : null}
          </div>
        </article>
      );
    },
  };
}
