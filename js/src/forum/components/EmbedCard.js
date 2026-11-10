import app from 'flarum/forum/app';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import { t, buildUrl, categoryColor, STAGE_ICONS } from '../util';

/**
 * A build's small card, from the plain data the server sends with posts
 * (garageEmbeds) and users (garageSignature). Inline elements only: it is
 * drawn inside a post's paragraph, whose children Mithril does not track.
 * Every text is set as text, never HTML.
 */
export default function EmbedCard({ attrs }) {
  return {
    view() {
      const card = attrs.card;
      const color = categoryColor(card.category?.color);

      return (
        <Link href={buildUrl(card)} className={'GarageEmbed' + (attrs.compact ? ' GarageEmbed--compact' : '')} style={{ '--garage-accent': color }}>
          <span className="GarageEmbed-cover">
            {card.cover ? <img src={card.cover} alt="" loading="lazy" /> : <Icon name={card.category?.icon || 'fas fa-warehouse'} />}
          </span>
          <span className="GarageEmbed-body">
            <span className="GarageEmbed-kicker">
              <Icon name="fas fa-warehouse" /> {attrs.compact ? t('signature.label') : card.category?.name || t('nav')}
            </span>
            <span className="GarageEmbed-title">{card.title}</span>
            {card.tagline && !attrs.compact ? <span className="GarageEmbed-tagline">{card.tagline}</span> : null}
            <span className="GarageEmbed-meta">
              {card.user ? <span>{t('card.by', { username: card.user.displayName })}</span> : null}
              <span>
                <Icon name={STAGE_ICONS[card.stage] || STAGE_ICONS.in_progress} /> {t(`stage.${card.stage}`)}
              </span>
              {card.entryCount ? <span>{t('card.updates', { count: card.entryCount })}</span> : null}
              {card.likeCount ? (
                <span>
                  <Icon name="fas fa-heart" /> {card.likeCount}
                </span>
              ) : null}
            </span>
          </span>
        </Link>
      );
    },
  };
}
