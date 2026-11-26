<?php

/*
 * Garage: members show off their builds, whatever the forum is about.
 *
 * A build has a photo gallery (the first photo is the cover), a spec sheet
 * whose fields the admin designs per category, a story and a dated build log.
 * Members like, follow and comment on builds; followers are told about new
 * log entries. A build linked on a line of its own in a post shows as a card,
 * and with FoF Signature a member can show one under their posts.
 *
 * The pages are lazy chunks: a forum page that is not Garage loads only the
 * routes, the sidebar link, the profile tab and the post card.
 */

use ErnestDefoe\Garage\Access;
use ErnestDefoe\Garage\Api;
use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Gdpr\GarageData;
use ErnestDefoe\Garage\Comment;
use ErnestDefoe\Garage\Entry;
use ErnestDefoe\Garage\Member;
use ErnestDefoe\Garage\Extend\Feature;
use ErnestDefoe\Garage\Http\GaragePageContent;
use ErnestDefoe\Garage\Listener\ForgetDeletedUser;
use ErnestDefoe\Garage\Notification\CommentBlueprint;
use ErnestDefoe\Garage\Notification\NewEntryBlueprint;
use ErnestDefoe\Garage\Notification\TeamInviteBlueprint;
use ErnestDefoe\Garage\Notification\TeamJoinedBlueprint;
use ErnestDefoe\Garage\Search;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\PostResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Extend;
use Flarum\Foundation\Paths;
use Flarum\Gdpr\Extend\UserData;
use Flarum\Http\UrlGenerator;
use Flarum\Search\Database\DatabaseSearchDriver;
use Flarum\User\Event\Deleting;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // 🚨 Not optional: the pages and forms are chunks in here, and undeclared chunks 404.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/garage', 'garage', GaragePageContent::class)
        ->route('/garage/{id:\d+}[-{slug}]', 'garage.build', GaragePageContent::class)
        ->route('/u/{username}/garage', 'user.garage'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ernestdefoe-garage', __DIR__.'/views'),

    (new Extend\Filesystem())
        ->disk('ernestdefoe-garage', fn (Paths $paths, UrlGenerator $url) => [
            'root' => "$paths->public/assets/garage",
            'url' => $url->to('forum')->path('assets/garage'),
            'visibility' => 'public',
        ]),

    new Extend\ApiResource(Api\CategoryResource::class),
    new Extend\ApiResource(Api\BuildResource::class),
    new Extend\ApiResource(Api\EntryResource::class),
    new Extend\ApiResource(Api\CommentResource::class),
    new Extend\ApiResource(Api\MemberResource::class),

    (new Extend\ApiResource(UserResource::class))
        ->fields(Api\UserFields::class),

    (new Extend\ApiResource(PostResource::class))
        ->fields(Api\PostFields::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumFields::class),

    // String literals, not Settings constants: this runs while Flarum boots, and a
    // class this extension autoloads here fatals every request mid-deploy.
    (new Extend\Settings())
        ->default('ernestdefoe-garage.nav', true)
        ->default('ernestdefoe-garage.use_fof_upload', true)
        ->default('ernestdefoe-garage.max_upload_kb', 10240)
        ->default('ernestdefoe-garage.currency', '$')
        ->serializeToForum('garageNav', 'ernestdefoe-garage.nav', 'boolval')
        ->serializeToForum('garageCurrency', 'ernestdefoe-garage.currency', fn ($value) => mb_substr((string) $value, 0, 6))
        ->serializeToForum('garageMaxUploadKb', 'ernestdefoe-garage.max_upload_kb', 'intval'),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(Build::class, Search\BuildSearcher::class)
        ->addFilter(Search\BuildSearcher::class, Search\Filter\CategoryFilter::class)
        ->addFilter(Search\BuildSearcher::class, Search\Filter\UserFilter::class)
        ->addFilter(Search\BuildSearcher::class, Search\Filter\StageFilter::class)
        ->addFilter(Search\BuildSearcher::class, Search\Filter\FeaturedFilter::class)
        ->addFilter(Search\BuildSearcher::class, Search\Filter\PublishedFilter::class)
        ->addFilter(Search\BuildSearcher::class, Search\Filter\TextFilter::class)
        ->addFilter(Search\BuildSearcher::class, Search\Filter\SpecFilter::class)
        ->addFilter(Search\BuildSearcher::class, Search\Filter\TeamFilter::class)
        ->addSearcher(Entry::class, Search\EntrySearcher::class)
        ->addFilter(Search\EntrySearcher::class, Search\Filter\EntryBuildFilter::class)
        ->addSearcher(Comment::class, Search\CommentSearcher::class)
        ->addFilter(Search\CommentSearcher::class, Search\Filter\CommentBuildFilter::class)
        ->addSearcher(Member::class, Search\MemberSearcher::class)
        ->addFilter(Search\MemberSearcher::class, Search\Filter\MemberBuildFilter::class),

    (new Extend\ModelVisibility(Build::class))
        ->scope(Access\ScopeBuildVisibility::class),
    (new Extend\ModelVisibility(Entry::class))
        ->scope(Access\ScopeChildVisibility::class),
    (new Extend\ModelVisibility(Comment::class))
        ->scope(Access\ScopeChildVisibility::class),
    (new Extend\ModelVisibility(Member::class))
        ->scope(Access\ScopeMemberVisibility::class),

    (new Extend\Policy())
        ->modelPolicy(Build::class, Access\BuildPolicy::class)
        ->modelPolicy(Entry::class, Access\EntryPolicy::class)
        ->modelPolicy(Comment::class, Access\CommentPolicy::class)
        ->modelPolicy(Member::class, Access\MemberPolicy::class),

    (new Extend\Model(User::class))
        ->cast('garage_build_count', 'integer')
        ->cast('garage_signature_build_id', 'integer'),

    (new Extend\Notification())
        ->type(NewEntryBlueprint::class, ['alert', 'email'])
        ->type(CommentBlueprint::class, ['alert'])
        ->type(TeamInviteBlueprint::class, ['alert'])
        ->type(TeamJoinedBlueprint::class, ['alert']),

    (new Extend\Event())
        ->listen(Deleting::class, ForgetDeletedUser::class),

    // The flags the code branches on (Features), each on only while its extension is.
    (new Extend\Conditional())
        ->whenExtensionEnabled('fof-upload', fn () => [new Feature('fofUpload')])
        ->whenExtensionEnabled('ernestdefoe-scribe', fn () => [new Feature('scribe')])
        ->whenExtensionEnabled('flarum-markdown', fn () => [new Feature('otherEditor')])
        ->whenExtensionEnabled('fof-rich-text', fn () => [new Feature('otherEditor')])
        ->whenExtensionEnabled('flarum-gdpr', fn () => [
            (new UserData())->addType(GarageData::class),
        ])
        ->whenExtensionEnabled('fof-signature', fn () => [
            new Feature('fofSignature'),
            (new Extend\ApiResource(UserResource::class))
                ->fields(Api\SignatureFields::class),
        ]),
];
