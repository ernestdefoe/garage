<?php

namespace ErnestDefoe\Garage\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Garage\Job\NotifyFollowers;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * A new log entry tells the build's followers who can see it, a page of
 * followers per job: every page is checked, and one notification goes out.
 */
class NotifyFollowersTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('ernestdefoe-garage');

        $now = Carbon::now();
        $users = [$this->normalUser()];
        foreach ([3, 4, 5, 6] as $id) {
            $users[] = ['id' => $id, 'username' => "follower$id", 'email' => "follower$id@machine.local", 'is_email_confirmed' => 1];
        }

        $this->prepareDatabase([
            User::class => $users,
            // Only the Viewers group may see Garage: follower 6 is not in it.
            Group::class => [['id' => 10, 'name_singular' => 'Viewer', 'name_plural' => 'Viewers']],
            'group_user' => [['user_id' => 2, 'group_id' => 10], ['user_id' => 3, 'group_id' => 10], ['user_id' => 4, 'group_id' => 10], ['user_id' => 5, 'group_id' => 10]],
            'group_permission' => [['group_id' => 10, 'permission' => 'ernestdefoe-garage.view'], ['group_id' => 10, 'permission' => 'ernestdefoe-garage.create']],
            'garage_categories' => [['id' => 1, 'name' => 'Cars', 'slug' => 'cars', 'position' => 0, 'build_count' => 1]],
            'garage_builds' => [[
                'id' => 1, 'user_id' => 2, 'category_id' => 1, 'title' => 'Build', 'slug' => 'build', 'stage' => 'in_progress',
                'published_at' => $now, 'last_activity_at' => $now, 'entry_count' => 0, 'like_count' => 0, 'comment_count' => 0, 'follower_count' => 5,
            ]],
            // The owner follows too, and is never told about their own update.
            'garage_follows' => array_map(fn ($id) => ['build_id' => 1, 'user_id' => $id, 'created_at' => $now], [2, 3, 4, 5, 6]),
        ]);
    }

    /**
     * In this test the queue is synchronous: each recipient's email job
     * (core's EmailNotificationDriver, one job per recipient) restores its
     * user as it runs. On a forum those run on the queue, one per worker pass.
     */
    protected function allowedRepeatedQueries(): array
    {
        return ['users"."id" = ? limit ?', 'users`.`id` = ? limit ?'];
    }

    protected function tearDown(): void
    {
        NotifyFollowers::$page = 200;
        parent::tearDown();
    }

    #[Test]
    public function followers_are_checked_a_page_at_a_time_and_told_once()
    {
        $this->database()->table('group_permission')->whereIn('group_id', [Group::GUEST_ID, Group::MEMBER_ID])->where('permission', 'like', 'ernestdefoe-garage.%')->delete();
        NotifyFollowers::$page = 2;

        $response = $this->send($this->request('POST', '/api/garage-entries', ['authenticatedAs' => 2, 'json' => ['data' => [
            'type' => 'garage-entries',
            'attributes' => ['title' => 'New wheels'],
            'relationships' => ['build' => ['data' => ['type' => 'garage-builds', 'id' => '1']]],
        ]]]));
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        $told = $this->database()->table('notifications')->where('type', 'garageNewEntry')->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([3, 4, 5], $told);
    }
}
