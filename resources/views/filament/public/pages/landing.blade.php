<x-filament-panels::page.simple>
    <main class="grid w-full overflow-hidden md:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="flex flex-col justify-center">
            <h1 class="mb-3 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">
                Зборівська громада
            </h1>
            <p class="mb-8 text-sm leading-6 text-gray-600">
                Тернопільська область<br>
                Тернопільський район
            </p>

            <nav aria-label="Корисні посилання">
                <ul class="space-y-4">
                    <li>
                        <a
                            href="https://zborivska-gromada.gov.ua/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-primary-600 underline underline-offset-4 hover:text-primary-700"
                        >
                            Офіційний сайт громади
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://data.gov.ua/organization/zborivska-miska-rada"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-primary-600 underline underline-offset-4 hover:text-primary-700"
                        >
                            Відкриті дані громади
                        </a>
                    </li>
                </ul>
            </nav>

            <x-filament::button
                :href="route('filament.rada.auth.login')"
                tag="a"
                icon="heroicon-m-arrow-right"
                icon-position="after"
                class="mt-10 w-fit"
            >
                Увійти до системи
            </x-filament::button>
        </section>

        <div class="flex min-h-40 items-center justify-center md:min-h-full">
            <img
                src="{{ asset('img/council-emblem.png') }}"
                alt="Герб міської ради"
                class="h-auto max-h-72 max-w-full object-contain"
            >
        </div>
    </main>
</x-filament-panels::page.simple>
