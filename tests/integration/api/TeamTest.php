<?php

namespace ErnestDefoe\Garage\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Garage\Build;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * A build's team: the owner invites, the invitee accepts or declines, only
 * accepted members show, pending invites stay between the owner and the
 * invitee, and the owner and the member are told.
 */
class TeamTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('ernestdefoe-garage');

        $now = Carbon::now();
        $users = [$this->normalUser()];
        foreach (range(3, 24) as $id) {
            $users[] = ['id' => $id, 'username' => "member$id", 'email' => "member$id@machine.local", 'is_email_confirmed' => 1];
        }

        $build = ['category_id' => 1, 'stage' => 'complete', 'specs' => null, 'last_activity_at' => $now, 'entry_count' => 0, 'like_count' => 0, 'comment_count' => 0, 'follower_count' => 0];
        $this->prepareDatabase([
            User::class => $users,
            'garage_categories' => [['id' => 1, 'name' => 'Software', 'slug' => 'software', 'position' => 0, 'build_count' => 2]],
            'garage_builds' => [
                ['id' => 1, 'user_id' => 2, 'title' => 'Forum app', 'slug' => 'forum-app', 'published_at' => $now, 'finished_at' => $now] + $build,
                ['id' => 2, 'user_id' => 2, 'title' => 'Draft', 'slug' => 'draft', 'published_at' => null] + $build,
                ['id' => 3, 'user_id' => 4, 'title' => 'Other app', 'slug' => 'other-app', 'published_at' => $now] + $build,
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

    /** @return array{int, array<string, mixed>|null} */
    private function invite(int $as, int $user, int $build = 1, ?string $role = null): array
    {
        return $this->call('POST', '/api/garage-members', $as, ['data' => [
            'type' => 'garage-members',
            'attributes' => array_filter(['role' => $role]),
            'relationships' => [
                'user' => ['data' => ['type' => 'users', 'id' => (string) $user]],
                'build' => ['data' => ['type' => 'garage-builds', 'id' => (string) $build]],
            ],
        ]]);
    }

    /** @return array{int, array<string, mixed>|null} */
    private function patch(int $as, string $id, array $attributes): array
    {
        return $this->call('PATCH', "/api/garage-members/$id", $as, ['data' => ['type' => 'garage-members', 'id' => $id, 'attributes' => $attributes]]);
    }

    /** @return list<int> */
    private function notified(string $type): array
    {
        return $this->database()->table('notifications')->where('type', $type)->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<string> user ids of the build page's team */
    private function team(?int $as): array
    {
        [$status, $body] = $this->call('GET', '/api/garage-builds/1', $as);
        $this->assertSame(200, $status);

        return array_map(fn ($m) => $m['id'], $body['data']['relationships']['team']['data'] ?? []);
    }

    #[Test]
    public function the_owner_invites_and_the_invitee_accepts()
    {
        [$status, $body] = $this->invite(2, 3, role: '  Backend developer ');
        $this->assertSame(201, $status, json_encode($body));
        $id = $body['data']['id'];
        $this->assertSame('pending', $body['data']['attributes']['status']);
        $this->assertSame('Backend developer', $body['data']['attributes']['role']);
        $this->assertSame([3], $this->notified('garageTeamInvite'));

        // Pending: not on the team yet, for anyone.
        $this->assertSame([], $this->team(null));
        $this->assertSame([], $this->team(2));

        // The invitee sees their invite on the build page.
        [, $page] = $this->call('GET', '/api/garage-builds/1', 3);
        $this->assertSame($id, $page['data']['relationships']['viewerMembership']['data']['id'] ?? null);

        // Nobody else accepts for them, and the owner can't.
        [$status] = $this->patch(5, $id, ['status' => 'accepted']);
        $this->assertContains($status, [403, 404]);
        [$status] = $this->patch(2, $id, ['status' => 'accepted']);
        $this->assertNotSame(200, $status);

        [$status, $body] = $this->patch(3, $id, ['status' => 'accepted']);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('accepted', $body['data']['attributes']['status']);
        $this->assertSame([2], $this->notified('garageTeamJoined'));
        $this->assertSame([], $this->notified('garageTeamInvite'));
        $this->assertSame([$id], $this->team(null));
    }

    #[Test]
    public function only_the_owner_or_a_moderator_invites_and_never_to_a_draft_or_twice()
    {
        [$status] = $this->invite(5, 3);
        $this->assertSame(403, $status);

        [$status] = $this->invite(2, 3, 2);
        $this->assertSame(422, $status);

        [$status] = $this->invite(2, 2);
        $this->assertSame(422, $status);

        [$status] = $this->invite(2, 3);
        $this->assertSame(201, $status);
        [$status] = $this->invite(2, 3);
        $this->assertSame(422, $status);

        // An admin is a moderator.
        [$status] = $this->invite(1, 6);
        $this->assertSame(201, $status);
    }

    #[Test]
    public function pending_invites_are_seen_only_by_the_owner_and_the_invitee()
    {
        $this->invite(2, 3);

        $ids = fn (?int $as) => array_map(fn ($m) => (int) $m['relationships']['user']['data']['id'], $this->call('GET', '/api/garage-members', $as, query: ['filter' => ['build' => '1']])[1]['data'] ?? []);

        $this->assertSame([3], $ids(2));
        $this->assertSame([3], $ids(3));
        $this->assertSame([], $ids(5));
        $this->assertSame([], $ids(null));
    }

    #[Test]
    public function the_owner_sets_roles_and_removes_and_a_member_leaves()
    {
        $a = $this->invite(2, 3)[1]['data']['id'];
        $b = $this->invite(2, 5)[1]['data']['id'];
        $this->patch(3, $a, ['status' => 'accepted']);
        $this->patch(5, $b, ['status' => 'accepted']);

        [$status, $body] = $this->patch(2, $a, ['role' => 'QA']);
        $this->assertSame(200, $status);
        $this->assertSame('QA', $body['data']['attributes']['role']);

        // A member doesn't set their own role.
        [$status] = $this->patch(3, $a, ['role' => 'Lead']);
        $this->assertNotSame(200, $status);

        [$status] = $this->call('DELETE', "/api/garage-members/$a", 3);
        $this->assertSame(204, $status);
        [$status] = $this->call('DELETE', "/api/garage-members/$b", 6);
        $this->assertContains($status, [403, 404]);
        [$status] = $this->call('DELETE', "/api/garage-members/$b", 2);
        $this->assertSame(204, $status);

        $this->assertSame([], $this->team(null));
        $this->assertSame([], $this->notified('garageTeamJoined'));
    }

    #[Test]
    public function a_declined_invite_is_gone_with_its_notification()
    {
        $id = $this->invite(2, 3)[1]['data']['id'];

        [$status] = $this->call('DELETE', "/api/garage-members/$id", 3);
        $this->assertSame(204, $status);
        $this->assertSame([], $this->notified('garageTeamInvite'));
        [$status] = $this->invite(2, 3);
        $this->assertSame(201, $status);
    }

    #[Test]
    public function a_big_team_loads_in_the_same_queries_as_a_small_one()
    {
        // Eight accepted members: the repeated-query detector fails the test on one query per member.
        foreach (range(3, 10) as $user) {
            $id = $this->invite(2, $user, role: "Role $user")[1]['data']['id'];
            $this->patch($user, $id, ['status' => 'accepted']);
        }

        [$status, $body] = $this->call('GET', '/api/garage-builds/1', null);
        $this->assertSame(200, $status);
        $this->assertCount(8, $body['data']['relationships']['team']['data']);
        $roles = array_column(array_column(array_filter($body['included'], fn ($r) => $r['type'] === 'garage-members'), 'attributes'), 'role');
        $this->assertContains('Role 7', $roles);
        $this->assertCount(8, array_filter($body['included'], fn ($r) => $r['type'] === 'users' && (int) $r['id'] >= 3));
    }

    #[Test]
    public function team_builds_are_listed_and_featured_for_their_members()
    {
        $id = $this->invite(2, 3, role: 'Backend developer')[1]['data']['id'];
        $this->invite(4, 3, 3);
        $this->patch(3, $id, ['status' => 'accepted']);

        // The profile's Team builds: accepted only, not the pending invite to build 3.
        [, $body] = $this->call('GET', '/api/garage-builds', null, query: ['filter' => ['team' => '3']]);
        $this->assertSame(['1'], array_column($body['data'], 'id'));

        // The contract other extensions (Dossier) use.
        $this->app();
        $builds = Build::query()->featuring(3)->with('team')->get();
        $this->assertSame([1], $builds->pluck('id')->all());
        $this->assertSame('Backend developer', $builds->first()->roleOf(3));
        $this->assertSame([1, 2], Build::query()->featuring(2)->orderBy('id')->pluck('id')->all());
    }

    #[Test]
    public function deleting_a_build_takes_its_team_with_it()
    {
        $id = $this->invite(2, 3)[1]['data']['id'];
        $this->patch(3, $id, ['status' => 'accepted']);

        [$status] = $this->call('DELETE', '/api/garage-builds/1', 2);
        $this->assertSame(204, $status);
        $this->assertSame(0, $this->database()->table('garage_members')->count());
    }

    #[Test]
    public function a_team_stops_at_twenty_rows()
    {
        // An admin: no rate limit in the way.
        foreach (range(3, 22) as $user) {
            [$status] = $this->invite(1, $user);
            $this->assertSame(201, $status);
        }

        [$status, $body] = $this->invite(1, 23);
        $this->assertSame(422, $status);
        $this->assertStringContainsString('team.full', json_encode($body));
        $this->assertSame(20, $this->database()->table('garage_members')->count());
    }

    #[Test]
    public function a_member_can_leave_a_build_that_was_unpublished()
    {
        $id = $this->invite(2, 3)[1]['data']['id'];
        $this->patch(3, $id, ['status' => 'accepted']);
        $this->database()->table('garage_builds')->where('id', 1)->update(['published_at' => null]);

        [$status] = $this->call('DELETE', "/api/garage-members/$id", 3);
        $this->assertSame(204, $status);
    }

    #[Test]
    public function team_notifications_list_without_a_query_per_notification()
    {
        // Eight invites to member 3 from eight builds: the repeated-query detector fails on one query each.
        $now = Carbon::now();
        foreach (range(10, 17) as $build) {
            $this->database()->table('garage_builds')->insert(['id' => $build, 'user_id' => 2, 'category_id' => 1, 'title' => "App $build", 'slug' => "app-$build", 'stage' => 'complete', 'published_at' => $now, 'last_activity_at' => $now, 'entry_count' => 0, 'like_count' => 0, 'comment_count' => 0, 'follower_count' => 0]);
            $this->invite(2, 3, $build);
        }

        [$status, $body] = $this->call('GET', '/api/notifications', 3);
        $this->assertSame(200, $status);
        $this->assertCount(8, $body['data']);
        $members = array_filter($body['included'] ?? [], fn ($r) => $r['type'] === 'garage-members');
        $this->assertCount(8, $members);
        $this->assertArrayNotHasKey('canEdit', array_values($members)[0]['attributes']);
    }

    #[Test]
    public function an_invite_to_a_build_that_was_unpublished_cannot_be_accepted()
    {
        $id = $this->invite(2, 3)[1]['data']['id'];
        $this->database()->table('garage_builds')->where('id', 1)->update(['published_at' => null]);

        [$status] = $this->patch(3, $id, ['status' => 'accepted']);
        $this->assertNotSame(200, $status);
        $this->assertSame([], $this->notified('garageTeamJoined'));

        // Declining still works.
        [$status] = $this->call('DELETE', "/api/garage-members/$id", 3);
        $this->assertSame(204, $status);
    }
}
