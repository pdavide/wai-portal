@component('mail::message')
# {{ __("Modifica dell'indirizzo email") }}

@includeFirst(
    ['mail.partials.' . $locale . '.user.email_pa_changed_message', 'mail.partials.' . config('app.fallback_locale') . '.user.user_invited_message'],
    ['user' => $user, 'publicAdministration' => $publicAdministration]
)

@endcomponent
