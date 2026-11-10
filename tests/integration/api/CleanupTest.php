<?php

namespace ErnestDefoe\Garage\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Garage\Storage\Photos;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * What is left behind when things go: a deleted member, a deleted log entry
 * or comment, a moved build. And what a spec value or a list costs to read.
 */
class CleanupTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // The GDPR test runs with flarum/gdpr on; every other test without it (a plain delete).
        $this->extension(...(str_contains($this->name(), 'gdpr') ? ['flarum-gdpr', 'ernestdefoe-garage'] : ['ernestdefoe-garage']));

        $now = Carbon::now();
        $build = fn (int $id, int $user, int $category, ?string $specs = null) => [
            'id' => $id, 'user_id' => $user, 'category_id' => $category, 'title' => "Build $id", 'slug' => "build-$id", 'stage' => 'in_progress',
            'specs' => $specs, 'published_at' => $now, 'last_activity_at' => $now, 'entry_count' => 0, 'like_count' => 0, 'comment_count' => 0, 'follower_count' => 0,
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'leaver', 'email' => 'leaver@machine.local', 'is_email_confirmed' => 1],
            ],
            'garage_categories' => [
                ['id' => 1, 'name' => 'Cars', 'slug' => 'cars', 'position' => 0, 'build_count' => 2, 'fields' => json_encode([
                    ['key' => 'site', 'label' => 'Site', 'type' => 'text', 'card' => false],
                ])],
                ['id' => 2, 'name' => 'Bikes', 'slug' => 'bikes', 'position' => 1, 'build_count' => 0, 'fields' => json_encode([
                    ['key' => 'site', 'label' => 'Site', 'type' => 'link', 'card' => false],
                ])],
            ],
            'garage_builds' => [
                $build(1, 2, 1, json_encode(['site' => 'javascript:alert(1)'])),
                $build(2, 3, 1),
            ],
            'garage_entries' => [
                ['id' => 1, 'build_id' => 1, 'user_id' => 2, 'title' => 'Update', 'happened_on' => '2026-11-01', 'comment_count' => 1, 'created_at' => $now],
            ],
            'garage_comments' => [
                ['id' => 1, 'build_id' => 1, 'entry_id' => null, 'user_id' => 3, 'body' => 'On the build', 'created_at' => $now],
                ['id' => 2, 'build_id' => 1, 'entry_id' => 1, 'user_id' => 3, 'body' => 'On the update', 'created_at' => $now],
            ],
            'garage_likes' => [['build_id' => 1, 'user_id' => 3, 'created_at' => $now]],
            'garage_follows' => [['build_id' => 1, 'user_id' => 3, 'created_at' => $now]],
        ]);

        $this->database()->table('garage_builds')->where('id', 1)->update(['comment_count' => 2, 'like_count' => 1, 'follower_count' => 1, 'entry_count' => 1, 'last_entry_id' => 1]);
    }

    /** A photo stored on Garage's own disk for build $id, as an upload would be. */
    private function photo(int $build): array
    {
        $file = tempnam(sys_get_temp_dir(), 'garage').'.png';
        $image = imagecreatetruecolor(40, 30);
        imagepng($image, $file);
        $photo = $this->app()->getContainer()->make(Photos::class)->store($file, 'builds');
        $this->database()->table('garage_builds')->where('id', $build)->update(['photos' => json_encode([$photo])]);

        return $photo;
    }

    private function disk()
    {
        return $this->app()->getContainer()->make(Factory::class)->disk(Photos::DISK);
    }

    #[Test]
    public function deleting_a_member_removes_their_builds_photos_and_traces_and_fixes_the_counts()
    {
        $this->assertMemberGone();
    }

    #[Test]
    public function erasing_a_member_through_gdpr_does_the_same()
    {
        $this->assertMemberGone(['gdprMode' => 'anonymization']);
        $this->assertTrue((bool) $this->database()->table('users')->where('id', 3)->value('anonymized'), 'the member is anonymized, not deleted');
    }

    private function assertMemberGone(array $body = []): void
    {
        $photo = $this->photo(2);
        $this->assertTrue($this->disk()->exists($photo['path']));

        $response = $this->send($this->request('DELETE', '/api/users/3', array_filter(['authenticatedAs' => 1, 'json' => $body])));
        $this->assertSame(204, $response->getStatusCode(), substr((string) $response->getBody(), 0, 9000));

        $db = $this->database();
        $this->assertFalse($this->disk()->exists($photo['path']), 'the photo file is gone');
        $this->assertFalse($this->disk()->exists(Photos::small($photo['path'])));
        $this->assertSame(0, $db->table('garage_builds')->where('user_id', 3)->count());
        $this->assertSame(0, $db->table('garage_comments')->where('user_id', 3)->count());

        $other = $db->table('garage_builds')->where('id', 1)->first();
        $this->assertSame([0, 0, 0], [(int) $other->comment_count, (int) $other->like_count, (int) $other->follower_count]);
        $this->assertSame(0, (int) $db->table('garage_entries')->where('id', 1)->value('comment_count'));
        $this->assertSame(1, (int) $db->table('garage_categories')->where('id', 1)->value('build_count'));
    }

    #[Test]
    public function a_text_value_is_never_served_as_a_link_and_moved_builds_keep_only_what_fits()
    {
        // The field becomes a link: the old text value is dropped as it is read.
        $this->send($this->request('PATCH', '/api/garage-categories/1', ['authenticatedAs' => 1, 'json' => ['data' => [
            'type' => 'garage-categories', 'id' => '1', 'attributes' => ['fields' => [['key' => 'site', 'label' => 'Site', 'type' => 'link']]],
        ]]]));
        $body = json_decode((string) $this->send($this->request('GET', '/api/garage-builds/1'))->getBody(), true);
        $this->assertEquals([], (array) $body['data']['attributes']['specs']);

        // Builds moved by a category delete are cleaned against their new spec sheet.
        $this->database()->table('garage_builds')->where('id', 1)->update(['specs' => json_encode(['site' => 'javascript:alert(1)'])]);
        $this->send($this->request('DELETE', '/api/garage-categories/1', ['authenticatedAs' => 1]));
        $this->assertSame(2, (int) $this->database()->table('garage_builds')->where('id', 1)->value('category_id'));
        $this->assertNull($this->database()->table('garage_builds')->where('id', 1)->value('specs'));
    }

    #[Test]
    public function the_builds_list_costs_the_same_however_many_builds_it_shows()
    {
        $count = function (int $limit): int {
            $queries = 0;
            $this->app()->getContainer()->make(ConnectionInterface::class)->listen(function () use (&$queries) {
                $queries++;
            });
            $request = $this->request('GET', '/api/garage-builds', ['authenticatedAs' => 2])->withQueryParams(['page' => ['limit' => $limit]]);
            $this->assertSame(200, $this->send($request)->getStatusCode());

            return $queries;
        };

        // Both builds have a latest entry, whose canEdit asks its build's policy.
        $this->database()->table('garage_entries')->insert(['id' => 2, 'build_id' => 2, 'user_id' => 3, 'title' => 'Other', 'happened_on' => '2026-11-02', 'comment_count' => 0, 'created_at' => Carbon::now()]);
        $this->database()->table('garage_builds')->where('id', 2)->update(['last_entry_id' => 2]);

        $count(1); // warm up
        $one = $count(1);
        $two = $count(2);
        $this->assertSame($one, $two, "1 build: $one queries, 2 builds: $two");
    }

    #[Test]
    public function deleting_an_update_takes_its_comments_out_of_the_builds_count()
    {
        $this->send($this->request('DELETE', '/api/garage-entries/1', ['authenticatedAs' => 2]));

        $this->assertSame(0, $this->database()->table('garage_comments')->where('entry_id', 1)->count());
        $this->assertSame(1, (int) $this->database()->table('garage_builds')->where('id', 1)->value('comment_count'));
    }

    #[Test]
    public function deleting_a_comment_takes_back_its_notification()
    {
        $response = $this->send($this->request('POST', '/api/garage-comments', ['authenticatedAs' => 3, 'json' => ['data' => [
            'type' => 'garage-comments', 'attributes' => ['body' => 'Nice'],
            'relationships' => ['build' => ['data' => ['type' => 'garage-builds', 'id' => '1']]],
        ]]]));
        $id = json_decode((string) $response->getBody(), true)['data']['id'];
        $this->assertSame(1, $this->database()->table('notifications')->where('type', 'garageComment')->count());

        $this->send($this->request('DELETE', "/api/garage-comments/$id", ['authenticatedAs' => 3]));
        $this->assertSame(0, $this->database()->table('notifications')->where('type', 'garageComment')->count());
    }

    #[Test]
    public function a_photo_is_removed_by_its_url_and_a_stale_url_removes_nothing()
    {
        $photo = $this->photo(1);
        $url = $this->disk()->url($photo['path']);

        $this->send($this->request('POST', '/api/garage-builds/1/photos/remove', ['authenticatedAs' => 2, 'json' => ['url' => $url.'?stale']]));
        $this->assertNotNull($this->database()->table('garage_builds')->where('id', 1)->value('photos'));

        $this->send($this->request('POST', '/api/garage-builds/1/photos/remove', ['authenticatedAs' => 2, 'json' => ['url' => $url]]));
        $this->assertNull($this->database()->table('garage_builds')->where('id', 1)->value('photos'));
        $this->assertFalse($this->disk()->exists($photo['path']));
    }
}
