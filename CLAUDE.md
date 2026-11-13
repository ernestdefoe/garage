# Garage: working rules

- **Commit straight to `main` and push. Never open a pull request on this repo.** Commit subjects are conventional (`feat:`, `fix:`); the release drafter bumps from the subject.
- `js/dist` is committed and is what forums load. After any change under `js/src` or `less/pages.less`, run `npm run build` in `js/` (from a fresh `npm ci`) and commit the bundle; CI fails if it is stale.
- Publishing a release is Ernest's click. Nothing is announced until he says so. Every change goes to the Command Center for review first.

## Design rules that exist for a reason

- **Visibility is one scope** (`Access\ScopeBuildVisibility`): moderators see everything; others need `ernestdefoe-garage.view`, then see published builds by members they can see, plus their own drafts. Entries and comments follow their build (`ScopeChildVisibility`). Anything rendered outside a request (follower emails, cards in posts) checks the same scope for the person it is for.
- **Spec values are cleaned on the server** against the category's fields (`Category::cleanSpecs`): links must be http(s), choices must be an option, unknown keys are dropped. Field keys stay when a field is renamed.
- **Member text is plain text** unless it was written in Scribe. Stories, log entries and comments are rendered by `forum/text.js` from text nodes (paragraphs, line breaks, http(s) links). A story or update written in Scribe (`RichText`, when Scribe is enabled and Markdown/FoF Rich Text are off) is sent as `storySource`/`bodySource`, parsed by Flarum's formatter and stored as its XML with `story_formatted`/`body_formatted` set; it is shown from `storyHtml`/`bodyHtml`, the formatter's rendering. That is the only HTML the client trusts. The flag is never guessed from the value: plain text can be typed to look like formatter XML. The forms send the text field only when it was changed, so opening and saving never converts one kind into the other. Comments stay plain.
- **Batching:** `liked`/`followed` (`Api\ViewerState`) and build cards for posts and signatures (`Api\Cards`) are deferred values resolved in one batch per request. Their WeakMaps are STATIC: post fields, user fields and resources each get their own instance, and separate maps made separate batches. A post also queues its author's signature build so both load together.
- **Owners' groups** are eager-loaded with the builds and comments lists (`eagerLoadWhenIncluded(['user' => ['user.groups']])`): staff see groups, and without it each owner costs a query.
- **Resource constructors run on every API request**: only cheap services there; the queue, image manager and disks are resolved when used.
- **Rate limits** (`Api\RateLimit`) keep ONE cache key per member, overwritten: the file cache never deletes expired keys, so time-stamped keys would leave a file per member per day. Photos 20/min 300/day, comments 5/min 200/day, log entries 10/min 60/day; admins are exempt.
- **Photos**: Garage's own disk re-encodes to WebP (full 1920 + small 640). With FoF Upload on and the member allowed to upload there, the browser uploads to FoF first and Garage stores that file's URLs after checking it is the member's own image (`Photos::fromFof`).
- **Member cleanup runs on `Deleting`** (`Erasure`, also the GDPR type's erasure): by `Deleted` the foreign keys have removed the rows and the photo paths. Columns Garage adds to `users` must be nullable: flarum/gdpr's anonymization sets every column it doesn't know to null.
- **Text search uses `whereLike`**: PostgreSQL's `LIKE` is case-sensitive.
- **Spec filters** use a JSON path (`specs->key`), which Laravel compiles per database with the table prefix in place. Never `whereJsonContains` (it breaks on a prefixed SQLite).
- **Front end**: pages and windows are lazy chunks with namespaced ids; the page styles are their own chunk (`less/pages.less` → `src/forum/generated/pagesCss.js`, gitignored) loaded beside the first page or window. forum.js only wires routes, nav, profile tab, notifications, post cards and signature cards (`less/forum.less`).
- 🚨 A modal's `state` attribute is taken by Flarum (pass the page's state as `log`), and a modal that navigates away must `app.modal.close()` first: `hide()` waits for its fade, a page change cuts it short, and Flarum then refuses to close every later window.
- 🚨 In Flarum 2, `save()` on a new record returns the stored model; keep using the returned one.

## Testing

- `composer test:setup && composer test` (integration; locally `DB_DRIVER=sqlite DB_PREFIX=gt_` proves prefix safety), `composer analyse:phpstan`.
- `cd js && npm ci && npm run build && npm run format-check`.
- Browser flows: `~/github/scopes/garage-shots/behave.mjs` against the local forum `~/Herd/garagedev`.
