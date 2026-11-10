import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import PhotoManager, { PhotoQueue } from './PhotoManager';
import useStyles from '../styles';
import { t } from '../util';

const MAX_PHOTOS = 12;

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/**
 * Add or edit a log entry: what was done, when, what it cost and photos of
 * it. The build's followers are told about a new one.
 */
export default class EntryModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);
    useStyles();
    const entry = (this.entry = this.attrs.entry || null);
    this.headline = Stream(entry?.title() || '');
    this.body = Stream(entry?.body() || '');
    this.cost = Stream(entry?.cost() ?? '');
    this.happenedOn = Stream(entry ? String(entry.happenedOn()).slice(0, 10) : today());
    this.queue = new PhotoQueue(MAX_PHOTOS);
  }

  className() {
    return 'GarageEntryModal Modal--large';
  }

  title() {
    return t(this.entry ? 'entry_edit.title_edit' : 'entry_edit.title_new');
  }

  content() {
    const currency = app.forum.attribute('garageCurrency') ?? '$';

    return (
      <div className="Modal-body">
        <div className="Form GarageEdit">
          <div className="Form-group">
            <label for="garage-entry-title">{t('entry_edit.title')}</label>
            <input
              id="garage-entry-title"
              name="title"
              className="FormControl"
              maxlength="120"
              required
              bidi={this.headline}
              placeholder={extractText(t('entry_edit.title_placeholder'))}
            />
          </div>
          <div className="GarageEdit-row">
            <div className="Form-group">
              <label for="garage-entry-date">{t('entry_edit.date')}</label>
              <input id="garage-entry-date" name="happenedOn" className="FormControl" type="date" max={today()} bidi={this.happenedOn} />
            </div>
            <div className="Form-group">
              <label for="garage-entry-cost">
                {t('entry_edit.cost')} <span className="GarageEdit-unit">({currency.trim()})</span>
              </label>
              <input
                id="garage-entry-cost"
                name="cost"
                className="FormControl"
                type="number"
                min="0"
                step="any"
                inputmode="decimal"
                bidi={this.cost}
              />
            </div>
          </div>
          <div className="Form-group">
            <label for="garage-entry-body">{t('entry_edit.body')}</label>
            <textarea
              id="garage-entry-body"
              name="body"
              className="FormControl"
              rows="6"
              maxlength="20000"
              bidi={this.body}
              placeholder={extractText(t('entry_edit.body_placeholder'))}
            />
          </div>
          <fieldset className="GarageEdit-photos">
            <legend>{t('edit.photos')}</legend>
            <PhotoManager cover={false} model={this.entry} queue={this.queue} endpoint={this.entry ? this.endpoint(this.entry) : null} />
          </fieldset>
          <div className="Form-group GarageEdit-submit">
            <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={!this.headline().trim()}>
              {this.progress || t(this.entry ? 'entry_edit.save' : 'entry_edit.post')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  endpoint(entry) {
    return `${app.forum.attribute('apiUrl')}/garage-entries/${entry.id()}`;
  }

  async onsubmit(e) {
    e.preventDefault();
    this.loading = true;
    this.alertAttrs = null;

    // `log` is the page's build log (a modal's own `state` attribute is taken).
    const { build, log: state } = this.attrs;
    const isNew = !this.entry;
    let entry = this.entry || app.store.createRecord('garage-entries');
    const cost = String(this.cost()).trim();
    const data = {
      title: this.headline(),
      body: this.body() || null,
      cost: cost === '' ? null : Number(cost),
      happenedOn: this.happenedOn() || today(),
    };
    if (isNew) data.relationships = { build };

    try {
      // A new record comes back as the stored model: carry on with that one.
      entry = (await entry.save(data)) || entry;
      const failed = await this.queue.send(this.endpoint(entry), (n, total) => {
        this.progress = t('edit.uploading', { n, total });
        m.redraw();
      });
      if (failed.length) app.alerts.show({ type: 'error' }, t('edit.upload_failed', { names: failed.join(', ') }));

      if (isNew && state) state.entries = [entry, ...state.entries];
      if (state) state.entries.sort((a, b) => String(b.happenedOn()).localeCompare(String(a.happenedOn())) || b.id() - a.id());
      // The build's counts, latest entry and spend.
      await app.store.find('garage-builds', build.id());

      this.hide();
    } catch (error) {
      console.error(error);
      this.loading = false;
      this.progress = null;
      this.onerror(error);
    }
  }
}
