<x-mail::html.notification>
    <x-slot:body>
        <p>{{ $translator->trans('ernestdefoe-garage.email.new_entry.body', ['recipient_display_name' => $user->display_name, 'owner' => $blueprint->owner?->display_name ?? '', 'build' => $blueprint->build->title, 'title' => $blueprint->entry->title]) }}</p>
        <p><a href="{{ $url->to('forum')->route('garage.build', ['id' => $blueprint->build->id.'-'.$blueprint->build->slug]) }}">{{ $translator->trans('ernestdefoe-garage.email.new_entry.link') }}</a></p>
    </x-slot:body>
</x-mail::html.notification>
