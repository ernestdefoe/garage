import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import TextEditor from 'flarum/common/components/TextEditor';
import { LINK } from '../text';
import { t } from '../util';

/** Scribe's editor is on: its forum code has loaded, and the server takes its HTML. */
export const scribe = () => !!(app.forum.attribute('garageRichText') && flarum.extensions['ernestdefoe-scribe']);

const escape = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/**
 * Plain text as the HTML Scribe loads: the paragraphs, line breaks and links
 * text.js shows. Scribe would read text that isn't HTML as Markdown, so a `*`
 * or `#` in an old story would change on its first edit.
 */
export function plainToHtml(text) {
  return text
    .replace(/\r\n/g, '\n')
    .split(/\n{2,}/)
    .filter((p) => p.trim())
    .map((p) => {
      const lines = p.split('\n').map((l) =>
        l
          .split(LINK)
          .map((part, i) => (i % 2 ? `<a href="${escape(part)}">${escape(part)}</a>` : escape(part)))
          .join('')
      );
      return `<p>${lines.join('<br>')}</p>`;
    })
    .join('');
}

/**
 * A story's or an update's text box: Scribe's editor when it is on, otherwise
 * the plain textarea. `text` is the stored plain text and `source` Scribe's
 * HTML, when it was written in Scribe. onchange gets ('source', html) or
 * ('text', text): the form sends only what was changed, so opening and saving
 * never turns plain text into HTML, or Scribe's formatting into plain text.
 */
export default class TextField extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    // TextEditor attaches its editor driver here, as it would to the composer.
    this.holder = {};
    this.value = scribe() ? this.attrs.source || plainToHtml(this.attrs.text || '') : this.attrs.text || '';
  }

  view() {
    const { id, name, rows, placeholder, source, onchange } = this.attrs;

    if (scribe()) {
      return (
        <div className="GarageEdit-editor" id={id}>
          <TextEditor
            composer={this.holder}
            value={this.value}
            placeholder={placeholder}
            onchange={(html) => onchange('source', html)}
            onsubmit={() => {}}
          />
        </div>
      );
    }

    return [
      <textarea
        id={id}
        name={name}
        className="FormControl"
        rows={rows}
        maxlength="20000"
        value={this.value}
        placeholder={placeholder}
        oninput={(e) => onchange('text', (this.value = e.target.value))}
      />,
      // Written in Scribe, which is off now: an edit here keeps only the words.
      source ? <p className="helpText">{t('edit.formatted_help')}</p> : null,
    ];
  }
}
