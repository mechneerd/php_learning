<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Learn')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="map" :href="route('path')" :current="request()->routeIs('path')" wire:navigate>
                        {{ __('Learning Path') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="book-open" :href="route('book')" :current="request()->routeIs('book')" wire:navigate>
                        {{ __('Book') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('lessons')" :current="request()->routeIs('lessons*')" wire:navigate>
                        {{ __('Lessons') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Practice')" class="grid">
                    <flux:sidebar.item icon="code-bracket" :href="route('practice')" :current="request()->routeIs('practice')" wire:navigate>
                        {{ __('Practice') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('quiz')" :current="request()->routeIs('quiz')" wire:navigate>
                        {{ __('Quizzes') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bug-ant" :href="route('debug')" :current="request()->routeIs('debug')" wire:navigate>
                        {{ __('Debug Lab') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Reinforce')" class="grid">
                    <flux:sidebar.item icon="rectangle-stack" :href="route('flashcards')" :current="request()->routeIs('flashcards')" wire:navigate>
                        {{ __('Flashcards') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-path" :href="route('revision')" :current="request()->routeIs('revision')" wire:navigate>
                        {{ __('Revision') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('skills')" :current="request()->routeIs('skills')" wire:navigate>
                        {{ __('Skills') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('interview')" :current="request()->routeIs('interview')" wire:navigate>
                        {{ __('Interview') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Build')" class="grid">
                    <flux:sidebar.item icon="folder" :href="route('projects')" :current="request()->routeIs('projects')" wire:navigate>
                        {{ __('Projects') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="sparkles" :href="route('tutor')" :current="request()->routeIs('tutor')" wire:navigate>
                        {{ __('AI Tutor') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="magnifying-glass" :href="route('search')" :current="request()->routeIs('search')" wire:navigate>
                        {{ __('Search') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if (auth()->user()?->isAdmin())
                    <flux:sidebar.group :heading="__('Admin')" class="grid">
                        <flux:sidebar.item icon="cog" :href="route('admin.dashboard')" :current="request()->routeIs('admin.*')" wire:navigate>
                            {{ __('Admin Panel') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('admin.books')" :current="request()->routeIs('admin.books')" wire:navigate>
                            {{ __('Books') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
