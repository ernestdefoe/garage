# Garage

Members show off their builds, whatever your forum is about: cars, PC builds, keyboards, guitars, gardens, bikes, cameras. For Flarum 2.

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/ernestdefoe/garage.svg)](https://packagist.org/packages/ernestdefoe/garage)

![Garage](screenshots/garage.jpg)

- **A page for every build.** A big cover photo, a gallery with a full-screen viewer (arrows, Escape, swipe), the story, and a spec sheet. Stages from Planning to Finished, For sale and Retired.
- **Spec sheets you design.** Each category gets its own fields: text, number (with a unit like hp or kg), choice and link. Pick which ones show on the build cards, and which ones members can filter by. Renaming a field keeps every build's value.
- **A build log.** Dated updates with photos and what they cost, shown as a timeline. The build adds up what's been spent so far.
- **Follow, like, comment.** Members follow a build and get a notification (and an email, if they want one) for every new update. Comments go on the build or on a single update; the owner is told about each one.
- **The Garage page.** Category chips, search, filters by stage and by your filter fields, sorted by recently updated, newest, most liked, most followed or most updates, with moderator-picked featured builds on top.
- **On profiles.** A Garage tab with the member's builds, their updates, likes and spend. Drafts stay private until published.
- **In posts.** Paste a build's link on a line of its own and the post shows the build's card. "Start a discussion about it" opens the composer with the link already in.
- **Under signatures.** With [FoF Signature](https://github.com/FriendsOfFlarum/signature), members can show one build under their posts.
- **Photos.** Stored by Garage (re-encoded as WebP, location data stripped) or through [FoF Upload](https://github.com/FriendsOfFlarum/upload) when it's installed. Big phone photos are shrunk in the browser before they're sent.
- **GDPR**: with Flarum's GDPR extension, a member's builds, photos and comments are part of their data export and are removed on erasure.
- **Permissions** to view, add builds, like, comment and moderate. Guests can look by default.
- **Light on your forum.** The pages, windows and styles load only when a Garage page opens. Lists take the same handful of queries however many builds they show; build cards in posts and signatures cost two queries per page at most, and none on pages without them.

![A build page](screenshots/build.jpg)

## Install

```sh
composer require ernestdefoe/garage
```

Then set up your categories and their spec sheets on the extension's settings page.

## Update

```sh
composer update ernestdefoe/garage
php flarum migrate
php flarum cache:clear
```

## Links

- [Packagist](https://packagist.org/packages/ernestdefoe/garage)
- [GitHub](https://github.com/ernestdefoe/garage)

☕ If this saves you time, you can [buy me a coffee](https://buymeacoffee.com/ernestdefoe).
