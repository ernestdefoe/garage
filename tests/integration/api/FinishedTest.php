<?php

namespace ErnestDefoe\Garage\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;

/** When a build reached Finished: set by the stage, kept for sale, cleared back at work, backfilled once. */
class FinishedTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('ernestdefoe-garage');

        $build = fn (int $id, string $stage, ?string $published) => [
            'id' => $id, 'user_id' => 2, 'category_id' => 1, 'title' => "Build $id", 'slug' => "build-$id", 'stage' => $stage,
            'published_at' => $published, 'last_activity_at' => $published, 'created_at' => '2026-01-05 10:00:00',
            'entry_count' => 0, 'like_count' => 0, 'comment_count' => 0, 'follower_count' => 0,
        ];

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            'garage_categories' => [['id' => 1, 'name' => 'Cars', 'slug' => 'cars', 'position' => 0, 'build_count' => 3]],
            'garage_builds' => [
                $build(1, 'in_progress', '2026-02-01 12:00:00'),
                $build(2, 'complete', '2026-03-01 12:00:00'),
                $build(3, 'complete', null),
            ],
            'garage_entries' => [
                ['id' => 1, 'build_id' => 2, 'user_id' => 2, 'title' => 'Early', 'happened_on' => '2026-04-01', 'comment_count' => 0, 'created_at' => Carbon::now()],
                ['id' => 2, 'build_id' => 2, 'user_id' => 2, 'title' => 'Done', 'happened_on' => '2026-05-20', 'comment_count' => 0, 'created_at' => Carbon::now()],
            ],
        ]);
    }

    private function stage(string $stage): array
    {
        $response = $this->send($this->request('PATCH', '/api/garage-builds/1', ['authenticatedAs' => 2, 'json' => ['data' => [
            'type' => 'garage-builds', 'id' => '1', 'attributes' => ['stage' => $stage],
        ]]]));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true)['data']['attributes'];
    }

    #[Test]
    public function finishing_a_build_records_when_and_going_back_to_work_clears_it()
    {
        $this->assertNull($this->stage('planning')['finishedAt']);

        $finished = $this->stage('complete')['finishedAt'];
        $this->assertNotNull($finished);

        $this->assertSame($finished, $this->stage('for_sale')['finishedAt'], 'selling a finished build keeps the date');
        $this->assertSame($finished, $this->stage('complete')['finishedAt'], 'and finishing it again does not move it');

        $this->assertNull($this->stage('in_progress')['finishedAt']);
    }

    #[Test]
    public function builds_already_finished_are_backfilled_from_their_log_or_publishing()
    {
        $db = $this->database();
        try {
            $db->getSchemaBuilder()->table('garage_builds', fn (Blueprint $table) => $table->dropColumn('finished_at'));
            $migration = require __DIR__.'/../../../migrations/2026_11_12_000009_add_finished_at_to_garage_builds.php';
            $migration['up']($db->getSchemaBuilder());

            $at = fn (int $id) => ($v = $db->table('garage_builds')->where('id', $id)->value('finished_at')) ? substr((string) $v, 0, 10) : null;
            $this->assertNull($at(1), 'in progress: not finished');
            $this->assertSame('2026-05-20', $at(2), 'the latest log entry');
            $this->assertSame('2026-01-05', $at(3), 'a draft with no log: the day it was added');
        } finally {
            // On MySQL the schema change commits the test's transaction, so its rows would outlive it.
            $db->table('garage_entries')->whereIn('id', [1, 2])->delete();
            $db->table('garage_builds')->whereIn('id', [1, 2, 3])->delete();
            $db->table('garage_categories')->where('id', 1)->delete();
        }
    }
}
