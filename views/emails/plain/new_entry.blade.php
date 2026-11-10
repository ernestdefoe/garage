<x-mail::plain.notification>
{!! $translator->trans('ernestdefoe-garage.email.new_entry.body', ['recipient_display_name' => $user->display_name, 'owner' => $blueprint->owner?->display_name ?? '', 'build' => $blueprint->build->title, 'title' => $blueprint->entry->title]) !!}

{!! $url->to('forum')->route('garage.build', ['id' => $blueprint->build->id.'-'.$blueprint->build->slug]) !!}
</x-mail::plain.notification>
