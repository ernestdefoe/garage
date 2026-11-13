<?php

namespace ErnestDefoe\Garage\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * Stories and log updates written in Scribe: its HTML goes through Flarum's
 * formatter and comes out through its allow-list. Plain text stays plain text,
 * whatever it looks like, and Scribe's fields exist only while Scribe is on.
 */
class RichTextTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const HOSTILE = '<p>Hello <strong>there</strong></p><script>alert(1)</script>'
        .'<p><a href="javascript:alert(2)">click</a><img src="https://example.com/a.png" onerror="alert(3)"></p>'
        .'<p onclick="alert(4)">Second</p><iframe src="https://evil.example"></iframe>';

    protected function setUp(): void
    {
        parent::setUp();

        // Scribe's tests run with it on; the rest without it.
        $this->extension(...(str_contains($this->name(), 'without_scribe') ? ['ernestdefoe-garage'] : ['ernestdefoe-scribe', 'ernestdefoe-garage']));

        $now = Carbon::now();
        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'reader', 'email' => 'reader@machine.local', 'is_email_confirmed' => 1],
            ],
            'garage_categories' => [['id' => 1, 'name' => 'Cars', 'slug' => 'cars', 'position' => 0, 'build_count' => 1]],
            'garage_builds' => [[
                'id' => 1, 'user_id' => 2, 'category_id' => 1, 'title' => 'Build', 'slug' => 'build', 'stage' => 'in_progress',
                'story' => "Line one\nline two\n\n*not bold*", 'published_at' => $now, 'last_activity_at' => $now,
                'entry_count' => 2, 'like_count' => 0, 'comment_count' => 0, 'follower_count' => 0,
            ]],
            'garage_entries' => [
                ['id' => 1, 'build_id' => 1, 'user_id' => 2, 'title' => 'Update', 'body' => 'Plain update', 'happened_on' => '2026-11-01', 'comment_count' => 0, 'created_at' => $now],
                ['id' => 2, 'build_id' => 1, 'user_id' => 2, 'title' => 'Earlier', 'body' => 'Plain too', 'happened_on' => '2026-10-01', 'comment_count' => 0, 'created_at' => $now],
            ],
        ]);
    }

    private function patch(string $type, array $attributes, int $as = 2)
    {
        return $this->send($this->request('PATCH', "/api/$type/1", ['authenticatedAs' => $as, 'json' => ['data' => [
            'type' => $type, 'id' => '1', 'attributes' => $attributes,
        ]]]));
    }

    private function show(string $type, ?int $as = null): array
    {
        $response = $this->send($this->request('GET', "/api/$type/1", $as ? ['authenticatedAs' => $as] : []));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true)['data']['attributes'];
    }

    /** No element or attribute that runs anything: whatever wasn't allowed is shown as text. */
    private function assertSafe(string $html): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>');
        foreach ($dom->getElementsByTagName('*') as $element) {
            $this->assertNotContains(strtolower($element->nodeName), ['script', 'iframe', 'object', 'embed', 'style']);
            foreach ($element->attributes as $attribute) {
                $this->assertStringStartsNotWith('on', strtolower($attribute->name));
                $this->assertStringStartsNotWith('javascript:', strtolower(trim($attribute->value)));
            }
        }
    }

    #[Test]
    public function a_story_written_in_scribe_is_stored_formatted_and_rendered_through_the_allow_list()
    {
        $response = $this->patch('garage-builds', ['storySource' => self::HOSTILE]);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertTrue((bool) $this->database()->table('garage_builds')->where('id', 1)->value('story_formatted'));

        $owner = $this->show('garage-builds', 2);
        $this->assertStringContainsString('<strong>there</strong>', $owner['storyHtml']);
        $this->assertSafe($owner['storyHtml']);
        $this->assertStringStartsWith("Hello there\n\n", $owner['story'], 'the words, paragraphs kept');
        $this->assertStringEndsWith("click\n\nSecond", $owner['story']);
        $this->assertStringContainsString('<strong>there</strong>', $owner['storySource'], 'the owner gets the HTML to edit');

        // Readers get the rendering, never the source.
        foreach ([3, null] as $reader) {
            $attributes = $this->show('garage-builds', $reader);
            $this->assertSafe($attributes['storyHtml']);
            $this->assertArrayNotHasKey('storySource', $attributes);
        }
    }

    #[Test]
    public function a_log_update_written_in_scribe_is_formatted_in_the_log_too()
    {
        $this->assertSame(200, $this->patch('garage-entries', ['bodySource' => self::HOSTILE])->getStatusCode());

        $response = $this->send($this->request('GET', '/api/garage-entries')->withQueryParams(['filter' => ['build' => '1']]));
        $entry = json_decode((string) $response->getBody(), true)['data'][0]['attributes'];
        $this->assertStringContainsString('<strong>there</strong>', $entry['bodyHtml']);
        $this->assertSafe($entry['bodyHtml']);
    }

    #[Test]
    public function plain_text_stays_plain_even_shaped_like_formatter_xml()
    {
        $show = $this->show('garage-builds', 3);
        $this->assertSame("Line one\nline two\n\n*not bold*", $show['story']);
        $this->assertArrayNotHasKey('storyHtml', $show);

        $xml = '<r><html:script>alert(1)</html:script><URL url="javascript:alert(1)">x</URL></r>';
        $this->assertSame(200, $this->patch('garage-builds', ['story' => $xml])->getStatusCode());
        $this->assertFalse((bool) $this->database()->table('garage_builds')->where('id', 1)->value('story_formatted'));
        $show = $this->show('garage-builds', 3);
        $this->assertSame($xml, $show['story']);
        $this->assertArrayNotHasKey('storyHtml', $show);
    }

    #[Test]
    public function an_empty_editor_clears_the_story_and_plain_text_replaces_formatting()
    {
        $this->patch('garage-builds', ['storySource' => '<p><strong>Bold</strong></p>']);
        $this->patch('garage-builds', ['storySource' => '<p></p>']);
        $this->assertNull($this->database()->table('garage_builds')->where('id', 1)->value('story'));

        $this->patch('garage-builds', ['storySource' => '<p><strong>Bold</strong></p>']);
        $this->patch('garage-builds', ['story' => 'Just words']);
        $row = $this->database()->table('garage_builds')->where('id', 1)->first();
        $this->assertSame(['Just words', false], [$row->story, (bool) $row->story_formatted]);
    }

    #[Test]
    public function only_someone_who_may_edit_writes_the_source()
    {
        $this->assertNotSame(200, $this->patch('garage-builds', ['storySource' => '<p>Mine now</p>'], 3)->getStatusCode());
        $this->assertSame("Line one\nline two\n\n*not bold*", $this->database()->table('garage_builds')->where('id', 1)->value('story'));
    }

    #[Test]
    public function without_scribe_the_source_cannot_be_written_and_formatted_text_still_renders()
    {
        $this->assertNotSame(200, $this->patch('garage-builds', ['storySource' => '<p><strong>Bold</strong></p>'])->getStatusCode());
        $this->assertFalse((bool) $this->database()->table('garage_builds')->where('id', 1)->value('story_formatted'));

        // A story written while Scribe was on keeps its formatting after it is turned off.
        $xml = $this->app()->getContainer()->make(\Flarum\Formatter\Formatter::class)->parse('<p><strong>Bold</strong> words</p>');
        $this->database()->table('garage_builds')->where('id', 1)->update(['story' => $xml, 'story_formatted' => true]);
        $show = $this->show('garage-builds', 3);
        $this->assertStringContainsString('Bold', $show['storyHtml']);
        $this->assertSame('Bold words', $show['story']);
    }

    #[Test]
    public function the_forum_says_whether_scribe_is_the_editor()
    {
        $forum = json_decode((string) $this->send($this->request('GET', '/api', ['authenticatedAs' => 2]))->getBody(), true);
        $this->assertTrue($forum['data']['attributes']['garageRichText']);
    }

    #[Test]
    public function the_log_costs_the_same_however_many_formatted_updates_it_shows()
    {
        $this->patch('garage-entries', ['bodySource' => '<p><strong>One</strong></p>']);
        $xml = $this->database()->table('garage_entries')->where('id', 1)->value('body');
        $this->database()->table('garage_entries')->where('id', 2)->update(['body' => $xml, 'body_formatted' => true]);

        $count = function (int $limit): int {
            $queries = 0;
            $this->app()->getContainer()->make(ConnectionInterface::class)->listen(function () use (&$queries) {
                $queries++;
            });
            $request = $this->request('GET', '/api/garage-entries', ['authenticatedAs' => 2])->withQueryParams(['filter' => ['build' => '1'], 'page' => ['limit' => $limit]]);
            $this->assertSame(200, $this->send($request)->getStatusCode());

            return $queries;
        };

        $count(1); // warm up
        $this->assertSame($count(1), $count(2));
    }
}
