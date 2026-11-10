import app from 'flarum/admin/app';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import extractText from 'flarum/common/utils/extractText';

const t = (key, params = {}) => app.translator.trans(`ernestdefoe-garage.admin.categories.${key}`, params);
const TYPES = ['text', 'number', 'select', 'link'];

/**
 * The categories and the spec sheet of each. A category is edited as a draft
 * (a plain copy) and saved with its button, so half-typed changes never reach
 * members. A field keeps its key when renamed, so builds keep their values.
 */
export default class CategoriesEditor {
  oninit() {
    this.categories = null;
    this.open = null;
    this.draft = null;
    app.store.find('garage-categories').then((list) => {
      this.categories = [...list].sort((a, b) => a.position() - b.position());
      m.redraw();
    });
  }

  view() {
    return (
      <div className="Form-group GarageCategories">
        <h3>{t('title')}</h3>
        <p className="helpText">{t('help')}</p>
        {this.categories ? (
          <ul className="GarageCategories-list">
            {this.categories.map((category, i) => this.row(category, i))}
            {this.open === 'new' ? <li className="GarageCategories-item is-open">{this.form(null)}</li> : null}
          </ul>
        ) : (
          <LoadingIndicator />
        )}
        {this.open !== 'new' ? (
          <Button className="Button" icon="fas fa-plus" onclick={() => this.edit(null)}>
            {t('add')}
          </Button>
        ) : null}
      </div>
    );
  }

  row(category, i) {
    const isOpen = this.open === category;
    return (
      <li className={'GarageCategories-item' + (isOpen ? ' is-open' : '')} key={category.id()}>
        <div className="GarageCategories-summary">
          <span className="GarageCategories-icon" style={{ background: category.color() || '#888' }}>
            <Icon name={category.icon() || 'fas fa-warehouse'} />
          </span>
          <span className="GarageCategories-name">
            <strong>{category.name()}</strong>
            <span className="helpText">{t('summary', { fields: (category.fields() || []).length, builds: category.buildCount() || 0 })}</span>
          </span>
          <span className="GarageCategories-tools">
            <Button
              className="Button Button--icon"
              icon="fas fa-arrow-up"
              disabled={i === 0}
              aria-label={extractText(t('move_up'))}
              onclick={() => this.move(i, -1)}
            />
            <Button
              className="Button Button--icon"
              icon="fas fa-arrow-down"
              disabled={i === this.categories.length - 1}
              aria-label={extractText(t('move_down'))}
              onclick={() => this.move(i, 1)}
            />
            <Button
              className="Button"
              icon={isOpen ? 'fas fa-chevron-up' : 'fas fa-pen'}
              onclick={() => (isOpen ? (this.open = null) : this.edit(category))}
            >
              {t(isOpen ? 'close' : 'edit')}
            </Button>
          </span>
        </div>
        {isOpen ? this.form(category) : null}
      </li>
    );
  }

  edit(category) {
    this.open = category || 'new';
    this.draft = {
      name: category?.name() || '',
      description: category?.description() || '',
      icon: category?.icon() || 'fas fa-screwdriver-wrench',
      color: category?.color() || '#c2410c',
      fields: JSON.parse(JSON.stringify(category?.fields() || [])).map((f) => ({ ...f, optionsText: (f.options || []).join('\n') })),
    };
  }

  form(category) {
    const d = this.draft;
    const types = {};
    TYPES.forEach((type) => (types[type] = extractText(t(`types.${type}`))));

    return (
      <div className="GarageCategories-form">
        <div className="GarageCategories-grid">
          <label>
            {t('name')}
            <input className="FormControl" maxlength="80" value={d.name} oninput={(e) => (d.name = e.target.value)} />
          </label>
          <label>
            {t('icon')}
            <input className="FormControl" maxlength="60" value={d.icon} placeholder="fas fa-car" oninput={(e) => (d.icon = e.target.value)} />
          </label>
          <label>
            {t('color')}
            <span className="GarageCategories-color">
              <input type="color" value={/^#[0-9a-f]{6}$/i.test(d.color) ? d.color : '#c2410c'} oninput={(e) => (d.color = e.target.value)} />
              <input className="FormControl" maxlength="9" value={d.color} oninput={(e) => (d.color = e.target.value)} />
            </span>
          </label>
        </div>
        <p className="helpText">{t('icon_help')}</p>
        <label>
          {t('description')}
          <input className="FormControl" maxlength="300" value={d.description} oninput={(e) => (d.description = e.target.value)} />
        </label>

        <h4>{t('fields')}</h4>
        <p className="helpText">{t('fields_help')}</p>
        <ol className="GarageFields">
          {d.fields.map((field, i) => (
            <li className="GarageFields-item">
              <div className="GarageFields-main">
                <input
                  className="FormControl"
                  maxlength="60"
                  value={field.label}
                  placeholder={extractText(t('field_label'))}
                  aria-label={extractText(t('field_label'))}
                  oninput={(e) => (field.label = e.target.value)}
                />
                <Select options={types} value={field.type} onchange={(type) => (field.type = type)} aria-label={extractText(t('field_type'))} />
                <Button
                  className="Button Button--icon"
                  icon="fas fa-arrow-up"
                  disabled={i === 0}
                  aria-label={extractText(t('move_up'))}
                  onclick={() => this.swap(d.fields, i, i - 1)}
                />
                <Button
                  className="Button Button--icon"
                  icon="fas fa-arrow-down"
                  disabled={i === d.fields.length - 1}
                  aria-label={extractText(t('move_down'))}
                  onclick={() => this.swap(d.fields, i, i + 1)}
                />
                <Button
                  className="Button Button--icon"
                  icon="far fa-trash-alt"
                  aria-label={extractText(t('remove_field'))}
                  onclick={() => d.fields.splice(i, 1)}
                />
              </div>
              {field.type === 'select' ? (
                <label className="GarageFields-extra">
                  {t('options')}
                  <textarea className="FormControl" rows="3" value={field.optionsText || ''} oninput={(e) => (field.optionsText = e.target.value)} />
                  <span className="helpText">{t('options_help')}</span>
                </label>
              ) : null}
              {field.type === 'number' ? (
                <label className="GarageFields-extra GarageFields-unit">
                  {t('unit')}
                  <input
                    className="FormControl"
                    maxlength="12"
                    value={field.unit || ''}
                    placeholder="hp, kg, mm…"
                    oninput={(e) => (field.unit = e.target.value)}
                  />
                </label>
              ) : null}
              <div className="GarageFields-flags">
                {field.type !== 'link' ? (
                  <label className="checkbox">
                    <input type="checkbox" checked={!!field.card} onchange={(e) => (field.card = e.target.checked)} />
                    {t('card')}
                  </label>
                ) : null}
                {field.type === 'select' ? (
                  <label className="checkbox">
                    <input type="checkbox" checked={!!field.filter} onchange={(e) => (field.filter = e.target.checked)} />
                    {t('filter')}
                  </label>
                ) : null}
              </div>
            </li>
          ))}
        </ol>
        <Button className="Button" icon="fas fa-plus" onclick={() => d.fields.push({ label: '', type: 'text', card: false })}>
          {t('add_field')}
        </Button>

        <div className="GarageCategories-actions">
          <Button className="Button Button--primary" loading={this.saving} disabled={!d.name.trim()} onclick={() => this.save(category)}>
            {t('save')}
          </Button>
          <Button className="Button" onclick={() => (this.open = null)}>
            {t('cancel')}
          </Button>
          {category ? (
            <Button className="Button Button--danger GarageCategories-delete" icon="far fa-trash-alt" onclick={() => this.remove(category)}>
              {t('delete')}
            </Button>
          ) : null}
        </div>
      </div>
    );
  }

  swap(list, a, b) {
    [list[a], list[b]] = [list[b], list[a]];
  }

  save(category) {
    const d = this.draft;
    const fields = d.fields.map(({ optionsText, ...field }) => ({
      ...field,
      options:
        field.type === 'select'
          ? (optionsText || '')
              .split('\n')
              .map((o) => o.trim())
              .filter(Boolean)
          : undefined,
    }));
    this.saving = true;

    (category || app.store.createRecord('garage-categories'))
      .save({ name: d.name, description: d.description || null, icon: d.icon || null, color: d.color || null, fields })
      .then((saved) => {
        if (!category) this.categories.push(saved);
        this.open = null;
        app.alerts.show({ type: 'success' }, t('saved'));
      })
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }

  move(i, step) {
    this.swap(this.categories, i, i + step);
    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/garage-categories/order',
      body: { ids: this.categories.map((c) => c.id()) },
    });
  }

  remove(category) {
    const others = this.categories.filter((c) => c !== category);
    const message = category.buildCount()
      ? others.length
        ? t('delete_confirm_move', { name: category.name(), count: category.buildCount(), to: others[0].name() })
        : t('delete_last')
      : t('delete_confirm', { name: category.name() });

    if (category.buildCount() && !others.length) return alert(extractText(message));
    if (!confirm(extractText(message))) return;

    category.delete().then(() => {
      this.categories = others;
      this.open = null;
      if (category.buildCount()) app.store.find('garage-categories');
      m.redraw();
    });
  }
}
