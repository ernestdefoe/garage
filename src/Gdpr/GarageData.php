<?php

namespace ErnestDefoe\Garage\Gdpr;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Comment;
use ErnestDefoe\Garage\Entry;
use ErnestDefoe\Garage\Erasure;
use ErnestDefoe\Garage\RichText;
use ErnestDefoe\Garage\Storage\Photos;
use Flarum\Gdpr\Data\Type;

/**
 * A member's Garage in their data export (builds, log entries, comments and
 * the photos Garage stores), and all of it gone on erasure. Registered only
 * when flarum/gdpr is enabled.
 */
class GarageData extends Type
{
    public static function exportDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-garage.lib.gdpr.export');
    }

    public static function anonymizeDescription(): string
    {
        return self::deleteDescription();
    }

    public static function deleteDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-garage.lib.gdpr.delete');
    }

    public function export(): ?array
    {
        $photos = resolve(Photos::class);
        $text = resolve(RichText::class);
        $export = [];

        foreach (Build::query()->where('user_id', $this->user->id)->get() as $build) {
            $folder = "garage/build-$build->id";
            $entries = Entry::query()->where('build_id', $build->id)->orderBy('happened_on')->get();

            $export[] = ["$folder/build.json" => $this->encodeForExport([
                ...$build->only(['title', 'tagline']),
                'story' => $text->text($build->story, $build->story_formatted, $build),
                ...$build->only(['stage', 'specs', 'spent', 'published_at', 'created_at', 'updated_at']),
                'photos' => $photos->presentAll($build->photos),
                'log' => $entries->map(fn (Entry $e) => [...$e->only(['title']), 'body' => $text->text($e->body, $e->body_formatted, $e), ...$e->only(['cost', 'happened_on', 'created_at']), 'photos' => $photos->presentAll($e->photos)])->all(),
            ])];

            foreach ([...($build->photos ?? []), ...$entries->flatMap(fn (Entry $e) => $e->photos ?? [])->all()] as $i => $photo) {
                if ($bytes = $photos->contents($photo)) {
                    $export[] = ["$folder/photo-".($i + 1).'.webp' => $bytes];
                }
            }
        }

        $comments = Comment::query()->where('user_id', $this->user->id)->orderBy('id')->get(['build_id', 'entry_id', 'body', 'created_at', 'edited_at']);
        if ($comments->isNotEmpty()) {
            $export[] = ['garage/comments.json' => $this->encodeForExport($comments->toArray())];
        }

        return $export;
    }

    public function anonymize(): void
    {
        // Builds are photos and stories of the member's own things: nothing to keep without them.
        $this->delete();
    }

    public function delete(): void
    {
        resolve(Erasure::class)->forget($this->user);
    }
}
