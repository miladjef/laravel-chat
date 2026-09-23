<div class="w-full min-h-svh grid lg:grid-cols-2 relative p-4 dark:bg-neutral-950">
    <section class="flex flex-col items-center justify-center gap-8 py-8">
        <header class="flex flex-col items-center justify-start gap-2 relative text-center">
            <p class="absolute text-secondary-100 dark:text-secondary-900/80 font-black text-5xl sm:text-6xl -top-5 select-none">
                OKKIO CHAT
            </p>
            <h1 class="font-bold text-secondary-600 dark:text-secondary-300 text-lg z-10">
                اوکیو، گفت‌وگوی ناشناس
            </h1>
            <p class="text-sm text-secondary-600 dark:text-secondary-400">
                برای حفظ حریم خصوصی، با یک شناسه ناشناس وارد می‌شوی
            </p>
        </header>

        <main class="w-full max-w-sm">
            <form wire:submit.prevent="submit" class="flex flex-col gap-4 w-full">
                <div class="flex flex-col gap-2 w-full">
                    <label for="input-okkio-name" class="w-full text-secondary-700 dark:text-secondary-300">
                        نام نمایشی
                        <span class="text-rose-500">*</span>
                    </label>
                    <input wire:model="display_name" id="input-okkio-name" type="text" minlength="3" maxlength="16"
                           autocomplete="off" placeholder="برنامه نویس بی حوصله"
                           class="w-full rounded px-4 py-2 outline-none ring-1 ring-secondary-100 focus:ring-primary-500 transition-all duration-300 dark:bg-secondary-900 dark:ring-secondary-800 dark:placeholder:text-secondary-500 dark:text-secondary-300">
                    <p class="text-xs text-secondary-500 dark:text-secondary-400">
                        شماره موبایل، ایمیل، نام کامل یا اطلاعات حساس را در نام نمایشی وارد نکن.
                    </p>
                    @error('display_name')
                    <p class="text-sm text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="bg-primary-600 w-full px-4 py-2 rounded-md text-white hover:bg-primary-500 transition-all duration-300 dark:bg-primary-700 dark:hover:bg-primary-600">
                    ورود به تالار گفت‌وگو
                </button>
            </form>
        </main>

        <footer class="text-center space-y-1">
            <p class="text-sm text-secondary-500 dark:text-secondary-400">Okkio Chat</p>
            <p class="text-xs text-secondary-400 dark:text-secondary-500" dir="ltr">
                Programmer: Miladjef
            </p>
        </footer>
    </section>

    <aside class="hidden lg:flex overflow-hidden relative items-center justify-center">
        <div class="w-full h-full overflow-hidden rounded-lg">
            <img class="w-full h-full object-center object-cover" src="{{ asset('images/okkio.jpg') }}" alt="Okkio Chat">
        </div>
        <div class="absolute inset-0 rounded-md bg-black bg-opacity-50"></div>
    </aside>
</div>
