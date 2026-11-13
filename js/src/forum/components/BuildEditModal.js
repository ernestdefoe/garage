import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import PhotoManager, { PhotoQueue } from './PhotoManager';
import TextField from './TextField';
import useStyles from '../styles';
import { t, buildUrl, STAGES } from '../util';

const MAX_PHOTOS = 24;

/**
 * Add or edit a build: what it is, its spec sheet (the fields of its
 * category), its story and its photos. A new build is saved first, then its
 * photos go up one by one; it is published at once unless the member keeps it
 * as a draft.
 */
export default class BuildEditModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);
    useStyles();
    const build = (this.build = this.attrs.build || null);
    this.categories = [...app.store.all('garage-categories')].sort((a, b) => a.position() - b.position());
    const category = build?.category() || this.attrs.category || this.categories[0] || null;

    this.categoryId = Stream(category ? category.id() : '');
    this.name = Stream(build?.title() || '');
    this.tagline = Stream(build?.tagline() || '');
    this.stage = Stream(build?.stage() || 'in_progress');
    // [field, value] once the story is changed: 'story' (plain) or 'storySource' (Scribe's HTML).
    this.storyEdit = null;
    this.specs = { ...(build?.specs() || {}) };
    this.publish = Stream(true);
    this.queue = new PhotoQueue(MAX_PHOTOS);

    if (!this.categories.length) {
      app.store.find('garage-categories').then((list) => {
        this.categories = [...list].sort((a, b) => a.position() - b.position());
        if (!this.categoryId() && this.categories[0]) this.categoryId(this.categories[0].id());
        m.redraw();
      });
    }
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    if (this.attrs.tab === 'photos') setTimeout(() => this.element.querySelector('.GarageEdit-photos')?.scrollIntoView({ block: 'start' }), 100);
  }

  className() {
    return 'GarageEditModal Modal--large';
  }

  title() {
    return t(this.build ? 'edit.title_edit' : 'edit.title_new');
  }

  category() {
    return this.categories.find((c) => c.id() === this.categoryId()) || null;
  }

  content() {
    const stages = {};
    STAGES.forEach((stage) => (stages[stage] = extractText(t(`stage.${stage}`))));
    const categories = {};
    this.categories.forEach((c) => (categories[c.id()] = c.name()));
    const fields = this.category()?.fields() || [];

    return (
      <div className="Modal-body">
        <div className="Form GarageEdit">
          <div className="GarageEdit-row">
            {this.categories.length > 1 ? (
              <div className="Form-group">
                <label for="garage-category">{t('edit.category')}</label>
                <Select id="garage-category" options={categories} value={this.categoryId()} onchange={this.categoryId} />
              </div>
            ) : null}
            <div className="Form-group">
              <label for="garage-stage">{t('edit.stage')}</label>
              <Select id="garage-stage" options={stages} value={this.stage()} onchange={this.stage} />
            </div>
          </div>
          <div className="Form-group">
            <label for="garage-title">{t('edit.title')}</label>
            <input
              id="garage-title"
              name="title"
              className="FormControl"
              maxlength="120"
              required
              bidi={this.name}
              placeholder={extractText(t('edit.title_placeholder'))}
            />
          </div>
          <div className="Form-group">
            <label for="garage-tagline">{t('edit.tagline')}</label>
            <input
              id="garage-tagline"
              name="tagline"
              className="FormControl"
              maxlength="160"
              bidi={this.tagline}
              placeholder={extractText(t('edit.tagline_placeholder'))}
            />
          </div>

          {fields.length ? (
            <fieldset className="GarageEdit-specs">
              <legend>{t('edit.specs')}</legend>
              <div className="GarageEdit-specGrid">{fields.map((field) => this.specField(field))}</div>
            </fieldset>
          ) : null}

          <div className="Form-group">
            <label for="garage-story">{t('edit.story')}</label>
            <TextField
              id="garage-story"
              name="story"
              rows="7"
              text={this.build?.story()}
              source={this.build?.storySource()}
              placeholder={extractText(t('edit.story_placeholder'))}
              onchange={(kind, value) => (this.storyEdit = [kind === 'source' ? 'storySource' : 'story', value])}
            />
          </div>

          <fieldset className="GarageEdit-photos">
            <legend>{t('edit.photos')}</legend>
            <PhotoManager model={this.build} queue={this.queue} endpoint={this.build ? this.endpoint(this.build) : null} />
          </fieldset>

          {!this.build ? (
            <label className="checkbox GarageEdit-publish">
              <input type="checkbox" checked={this.publish()} onchange={(e) => this.publish(e.target.checked)} />
              {t('edit.publish_now')}
            </label>
          ) : null}

          <div className="Form-group GarageEdit-submit">
            <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={!this.name().trim() || !this.categoryId()}>
              {this.progress || t(this.build ? 'edit.save' : 'edit.create')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  specField(field) {
    const id = `garage-spec-${field.key}`;
    const value = this.specs[field.key] ?? '';
    const set = (v) => (this.specs[field.key] = v);
    let control;

    if (field.type === 'select') {
      const options = { '': '—' };
      (field.options || []).forEach((option) => (options[option] = option));
      control = <Select id={id} options={options} value={String(value)} onchange={set} />;
    } else {
      control = (
        <input
          id={id}
          className="FormControl"
          type={field.type === 'number' ? 'number' : field.type === 'link' ? 'url' : 'text'}
          step={field.type === 'number' ? 'any' : undefined}
          inputmode={field.type === 'number' ? 'decimal' : undefined}
          maxlength={field.type === 'text' ? 120 : undefined}
          placeholder={field.type === 'link' ? 'https://' : undefined}
          value={value}
          oninput={(e) => set(e.target.value)}
        />
      );
    }

    return (
      <div className="Form-group">
        <label for={id}>
          {field.label}
          {field.type === 'number' && field.unit ? <span className="GarageEdit-unit"> ({field.unit})</span> : null}
        </label>
        {control}
      </div>
    );
  }

  endpoint(build) {
    return `${app.forum.attribute('apiUrl')}/garage-builds/${build.id()}`;
  }

  async onsubmit(e) {
    e.preventDefault();
    this.loading = true;
    this.alertAttrs = null;

    const isNew = !this.build;
    let build = this.build || app.store.createRecord('garage-builds');
    const data = {
      title: this.name(),
      tagline: this.tagline() || null,
      stage: this.stage(),
      specs: this.specs,
      relationships: { category: this.category() },
    };
    if (this.storyEdit) data[this.storyEdit[0]] = this.storyEdit[1] || null;
    // A new build is saved as a draft, and published once its photos are up: followers never see it half-done.
    if (isNew) data.published = false;

    try {
      // A new record comes back as the stored model: carry on with that one.
      build = (await build.save(data)) || build;
      const failed = await this.queue.send(this.endpoint(build), (n, total) => {
        this.progress = t('edit.uploading', { n, total });
        m.redraw();
      });
      if (isNew && this.publish()) await build.save({ published: true });
      if (failed.length) app.alerts.show({ type: 'error' }, t('edit.upload_failed', { names: failed.join(', ') }));

      // Going to the new page: close at once. hide() waits for its fade, which a page change
      // cuts short, and Flarum then keeps every later window from closing.
      if (isNew) {
        app.modal.close();
        m.route.set(buildUrl(build));
      } else {
        this.hide();
      }
    } catch (error) {
      console.error(error);
      this.loading = false;
      this.progress = null;
      this.onerror(error);
    }
  }
}
