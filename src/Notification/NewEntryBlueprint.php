<?php

namespace ErnestDefoe\Garage\Notification;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Entry;
use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/**
 * A build you follow has a new log entry. The subject is the build (it has an
 * API resource: a subject without one 500s every notification list); the
 * entry rides in the data.
 */
class NewEntryBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public Entry $entry, public Build $build, public ?User $owner)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->owner;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->build;
    }

    public function getData(): mixed
    {
        return ['entry' => $this->entry->id, 'title' => $this->entry->title];
    }

    public static function getType(): string
    {
        return 'garageNewEntry';
    }

    public static function getSubjectModel(): string
    {
        return Build::class;
    }

    public function getEmailViews(): array
    {
        return ['text' => 'ernestdefoe-garage::emails.plain.new_entry', 'html' => 'ernestdefoe-garage::emails.html.new_entry'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('ernestdefoe-garage.email.new_entry.subject', ['build' => $this->build->title]);
    }
}
