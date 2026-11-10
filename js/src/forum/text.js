/**
 * A member's plain text as paragraphs, with line breaks kept and web links
 * made clickable. Built from text nodes and Mithril elements, never HTML, so
 * nothing a member types can become markup.
 */
const LINK = /(https?:\/\/[^\s<>"']+[^\s<>"'.,;:!?)\]])/g;

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

export default function richText(text) {
  if (!text) return null;
  return text
    .replace(/\r\n/g, '\n')
    .split(/\n{2,}/)
    .filter((p) => p.trim())
    .map((paragraph) => <p>{paragraph.split('\n').flatMap((l, i) => (i ? [<br />, ...line(l)] : line(l)))}</p>);
}
