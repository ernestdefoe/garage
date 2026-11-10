<?php

namespace ErnestDefoe\Garage\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Who may see and change what: drafts stay private, members only touch their
 * own builds, spec sheets keep to the category's fields, and the counts and
 * notifications follow.
 */
class BuildTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-garage');

        $now = Carbon::now();
        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'other', 'email' => 'other@machine.local', 'is_email_confirmed' => 1],
            ],
            'garage_categories' => [
                ['id' => 1, 'name' => 'Cars', 'slug' => 'cars', 'position' => 0, 'build_count' => 0, 'fields' => json_encode([
                    ['key' => 'power', 'label' => 'Power', 'type' => 'number', 'unit' => 'hp', 'card' => true],
                    ['key' => 'drivetrain', 'label' => 'Drivetrain', 'type' => 'select', 'options' => ['RWD', 'AWD'], 'filter' => true, 'card' => false],
                    ['key' => 'site', 'label' => 'Site', 'type' => 'link', 'card' => false],
                ])],
            ],
            'garage_builds' => [
                ['id' => 1, 'user_id' => 2, 'category_id' => 1, 'title' => 'Published', 'slug' => 'published', 'stage' => 'in_progress', 'specs' => json_encode(['drivetrain' => 'AWD']), 'published_at' => $now, 'last_activity_at' => $now, 'entry_count' => 0, 'like_count' => 0, 'comment_count' => 0, 'follower_count' => 0],
                ['id' => 2, 'user_id' => 2, 'category_id' => 1, 'title' => 'Draft', 'slug' => 'draft', 'stage' => 'planning', 'specs' => null, 'published_at' => null, 'last_activity_at' => $now, 'entry_count' => 0, 'like_count' => 0, 'comment_count' => 0, 'follower_count' => 0],
            ],
        ]);
    }

    /** @return array{int, array<string, mixed>|null} */
    private function call(string $method, string $path, ?int $as, ?array $json = null, array $query = []): array
    {
        $request = $this->request($method, $path, array_filter(['authenticatedAs' => $as, 'json' => $json]));
        if ($query) {
            $request = $request->withQueryParams($query);
        }
        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function attributes(array $attributes, ?int $id = 1): array
    {
        return ['data' => array_filter(['type' => 'garage-builds', 'id' => $id ? (string) $id : null, 'attributes' => $attributes])];
    }

    #[Test]
    public function guests_see_published_builds_but_never_drafts()
    {
        [$status, $body] = $this->call('GET', '/api/garage-builds', null);
        $this->assertSame(200, $status);
        $this->assertSame(['1'], array_column($body['data'], 'id'));

        [$status] = $this->call('GET', '/api/garage-builds/2', null);
        $this->assertSame(404, $status);

        [$status] = $this->call('GET', '/api/garage-builds/2', 3);
        $this->assertSame(404, $status);

        // Its owner sees it.
        [$status] = $this->call('GET', '/api/garage-builds/2', 2);
        $this->assertSame(200, $status);
    }

    #[Test]
    public function a_member_cannot_change_someone_elses_build_or_its_log_or_photos()
    {
        [$status] = $this->call('PATCH', '/api/garage-builds/1', 3, $this->attributes(['title' => 'Mine now']));
        $this->assertSame(403, $status);

        [$status] = $this->call('POST', '/api/garage-entries', 3, ['data' => [
            'type' => 'garage-entries',
            'attributes' => ['title' => 'Sneaky update'],
            'relationships' => ['build' => ['data' => ['type' => 'garage-builds', 'id' => '1']]],
        ]]);
        $this->assertSame(403, $status);

        [$status] = $this->call('POST', '/api/garage-builds/1/photos/order', 3, ['order' => [0]]);
        $this->assertSame(403, $status);

        [$status] = $this->call('DELETE', '/api/garage-builds/1', 3);
        $this->assertSame(403, $status);

        $this->assertSame('Published', $this->database()->table('garage_builds')->where('id', 1)->value('title'));
    }

    #[Test]
    public function spec_sheets_keep_only_the_categorys_fields_and_values_that_fit()
    {
        [$status, $body] = $this->call('POST', '/api/garage-builds', 2, [
            'data' => [
                'type' => 'garage-builds',
                'attributes' => [
                    'title' => 'New build',
                    'published' => true,
                    'specs' => ['power' => '210', 'drivetrain' => 'FWD', 'site' => 'javascript:alert(1)', 'unknown' => 'x'],
                ],
                'relationships' => ['category' => ['data' => ['type' => 'garage-categories', 'id' => '1']]],
            ],
        ]);

        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame(['power' => 210], $body['data']['attributes']['specs']);

        // Counts follow (this and the published fixture; never the draft): the profile tab and the category.
        $this->assertSame(2, (int) $this->database()->table('users')->where('id', 2)->value('garage_build_count'));
        $this->assertSame(2, (int) $this->database()->table('garage_categories')->where('id', 1)->value('build_count'));
    }

    #[Test]
    public function members_like_and_follow_others_builds_but_not_their_own()
    {
        [$status, $body] = $this->call('PATCH', '/api/garage-builds/1', 3, $this->attributes(['liked' => true, 'followed' => true]));
        $this->assertSame(200, $status);
        $this->assertTrue($body['data']['attributes']['liked']);
        $this->assertSame(1, $body['data']['attributes']['likeCount']);
        $this->assertSame(1, $body['data']['attributes']['followerCount']);

        [$status] = $this->call('PATCH', '/api/garage-builds/1', 2, $this->attributes(['liked' => true]));
        $this->assertSame(403, $status);
    }

    #[Test]
    public function a_new_log_entry_tells_followers_and_a_comment_tells_the_owner()
    {
        $this->call('PATCH', '/api/garage-builds/1', 3, $this->attributes(['followed' => true]));

        [$status] = $this->call('POST', '/api/garage-entries', 2, ['data' => [
            'type' => 'garage-entries',
            'attributes' => ['title' => 'New wheels', 'cost' => 400],
            'relationships' => ['build' => ['data' => ['type' => 'garage-builds', 'id' => '1']]],
        ]]);
        $this->assertSame(201, $status);

        $build = $this->database()->table('garage_builds')->where('id', 1)->first();
        $this->assertSame(1, (int) $build->entry_count);
        $this->assertEquals(400, (float) $build->spent);

        [$status] = $this->call('POST', '/api/garage-comments', 3, ['data' => [
            'type' => 'garage-comments',
            'attributes' => ['body' => 'Nice wheels'],
            'relationships' => ['build' => ['data' => ['type' => 'garage-builds', 'id' => '1']]],
        ]]);
        $this->assertSame(201, $status);

        $notifications = $this->database()->table('notifications')->orderBy('id')->get(['user_id', 'type'])
            ->map(fn ($n) => $n->user_id.':'.$n->type)->all();
        $this->assertSame(['3:garageNewEntry', '2:garageComment'], $notifications);
    }

    #[Test]
    public function nobody_comments_on_a_draft_and_its_comments_stay_hidden()
    {
        [$status] = $this->call('POST', '/api/garage-comments', 3, ['data' => [
            'type' => 'garage-comments',
            'attributes' => ['body' => 'Hello'],
            'relationships' => ['build' => ['data' => ['type' => 'garage-builds', 'id' => '2']]],
        ]]);
        $this->assertContains($status, [403, 404]);
        $this->assertSame(0, $this->database()->table('garage_comments')->count());
    }

    #[Test]
    public function builds_filter_by_a_spec_value_on_a_prefixed_database()
    {
        [$status, $body] = $this->call('GET', '/api/garage-builds', null, null, ['filter' => ['spec' => 'drivetrain:AWD']]);
        $this->assertSame(200, $status);
        $this->assertSame(['1'], array_column($body['data'], 'id'));

        [, $body] = $this->call('GET', '/api/garage-builds', null, null, ['filter' => ['spec' => 'drivetrain:RWD']]);
        $this->assertSame([], $body['data']);

        [, $body] = $this->call('GET', '/api/garage-builds', null, null, ['filter' => ['text' => 'publ']]);
        $this->assertSame(['1'], array_column($body['data'], 'id'));
    }
}
