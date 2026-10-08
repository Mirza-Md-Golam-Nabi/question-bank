{{-- The language toggle as placed inside a Filament panel (user menu, and
     centred under the login card). The toggle itself is the shared
     <x-language-switcher> component. --}}
<div @class(['flex items-center', 'mt-6 justify-center' => $centered ?? false])>
    <x-language-switcher variant="panel" />
</div>
