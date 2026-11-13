/**
 * A member's text as it is shown. Written in Scribe, it is the HTML Flarum's
 * formatter rendered on the server through its allow-list, exactly as a post's
 * is, and styled as one (Post-body).
 *
 * Otherwise it is plain text as paragraphs, with line breaks kept and web links
 * made clickable. Built from text nodes and Mithril elements, never HTML, so
 * nothing a member types can become markup.
 */
export const LINK = /(https?:\/\/[^\s<>"']+[^\s<>"'.,;:!?)\]])/g;

function line(text) {
  return text.split(LINK).map((part, i) =>
    i % 2 ? (
      <a href={part} target="_blank" rel="ugc noopener nofollow">
        {part}
      </a>
    ) : (
      part
    )
  );
}

export default function richText(text, html) {
  if (html) return <div className="Post-body">{m.trust(html)}</div>;
  if (!text) return null;
  return text
    .replace(/\r\n/g, '\n')
    .split(/\n{2,}/)
    .filter((p) => p.trim())
    .map((paragraph) => <p>{paragraph.split('\n').flatMap((l, i) => (i ? [<br />, ...line(l)] : line(l)))}</p>);
}
