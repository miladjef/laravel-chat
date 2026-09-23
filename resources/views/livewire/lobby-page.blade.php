<div class="min-h-svh grid grid-cols-1 lg:grid-cols-12 p-3 sm:p-4 bg-gray-100 gap-3 sm:gap-4 dark:bg-secondary-950">
    <aside class="lg:col-span-4 xl:col-span-3 flex flex-col gap-3 sm:gap-4 min-h-0">
        <div class="bg-white p-4 ring-1 ring-secondary-200 rounded-md dark:bg-secondary-900 dark:ring-secondary-800">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 min-w-10 min-h-10 bg-secondary-200 dark:bg-secondary-800 rounded-md overflow-hidden">
                    <img src="{{ auth()->user()->avatar }}" alt="آواتار کاربر" class="w-full h-full object-center object-cover">
                </div>
                <div class="text-sm flex flex-col gap-1 truncate grow">
                    <p class="text-secondary-700 dark:text-secondary-300 truncate">
                        {{ auth()->user()->display_name }}
                    </p>
                    <p class="text-secondary-500 text-xs dark:text-secondary-400 truncate" dir="ltr">
                        {{ auth()->user()->uuid }}
                    </p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-rose-600 hover:text-rose-500 dark:text-rose-400">
                        خروج
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:flex-auto lg:h-0 max-h-52 lg:max-h-none bg-white ring-1 ring-secondary-200 rounded-md flex flex-col dark:bg-secondary-900 dark:ring-secondary-800">
            <div class="px-4 py-4 flex items-center justify-between gap-3">
                <p class="text-primary-600 dark:text-primary-500">کاربران آنلاین</p>
                <span id="online-users-count" class="text-xs text-secondary-500 dark:text-secondary-400">0</span>
            </div>
            <ul id="online-users-list" wire:ignore class="flex flex-col gap-4 flex-auto min-h-0 scroll overflow-y-auto px-4 pb-4 truncate"></ul>
        </div>
        <p class="text-center text-[11px] text-secondary-400 dark:text-secondary-600" dir="ltr">
            Programmer: Miladjef
        </p>
    </aside>

    <main class="lg:col-span-8 xl:col-span-9 flex flex-col items-center justify-between gap-3 sm:gap-4 min-h-[65svh] lg:min-h-0">
        <div id="chat-list-wrapper" wire:ignore
             class="flex-auto min-h-0 overflow-y-auto w-full scroll rounded-md ring-1 ring-secondary-200 p-4 bg-white dark:bg-secondary-900 dark:ring-secondary-800">
            <ul class="flex flex-col gap-4" id="chat-list">
                @foreach($this->systemMessages() as $systemMessage)
                    <li class="flex items-start gap-4">
                        <div class="min-w-10 min-h-10 w-10 h-10 bg-secondary-100 dark:bg-secondary-800 rounded-md overflow-hidden">
                            <img src="{{ avatar_data_uri('okkio-system') }}" alt="آواتار سیستم" class="w-full h-full object-center object-cover">
                        </div>
                        <div class="text-sm flex flex-col gap-1 min-w-0">
                            <p class="text-secondary-500 dark:text-secondary-400">سیستم اوکیوچت</p>
                            <p class="text-secondary-700 message dark:text-secondary-300">{{ $systemMessage }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-md ring-1 ring-secondary-200 w-full p-3 sm:p-4 bg-white dark:bg-secondary-900 dark:ring-secondary-800">
            <form id="form-message" class="flex flex-col sm:flex-row w-full gap-3 sm:gap-4" autocomplete="off">
                <label for="input-message" class="grow">
                    <span class="sr-only">پیام</span>
                    <input required maxlength="500" name="message" type="text" id="input-message"
                           class="w-full px-4 py-2 rounded-md outline-none bg-secondary-100 ring-1 ring-secondary-200 dark:bg-secondary-800 dark:ring-secondary-700 dark:placeholder:text-secondary-500 dark:text-secondary-300"
                           placeholder="پیام خود را بنویسید...">
                </label>
                <button id="btn-message" type="submit" class="bg-primary-600 dark:bg-primary-800 px-5 py-2 rounded-md text-white disabled:opacity-50 disabled:cursor-not-allowed">
                    ارسال پیام
                </button>
            </form>
            <p id="message-error" class="hidden text-sm text-rose-500 mt-2" role="alert"></p>
        </div>
    </main>
</div>

@script
<script>
    const chatListWrapper = document.getElementById('chat-list-wrapper');
    const chatList = document.getElementById('chat-list');
    const usersList = document.getElementById('online-users-list');
    const usersCount = document.getElementById('online-users-count');
    const inputMessage = document.getElementById('input-message');
    const messageForm = document.getElementById('form-message');
    const messageButton = document.getElementById('btn-message');
    const messageError = document.getElementById('message-error');
    const currentUserUuid = @js(auth()->user()->uuid);
    const currentUserAvatar = @js(auth()->user()->avatar);
    const systemAvatar = @js(avatar_data_uri('okkio-system'));

    const savedTheme = localStorage.getItem('okkio-theme');
    if (savedTheme === 'light') document.documentElement.classList.remove('dark');
    if (savedTheme === 'dark') document.documentElement.classList.add('dark');

    const lobbyChannel = Echo.join('lobby')
        .here(handleHereUsers)
        .joining(handleUserJoining)
        .leaving(handleUserLeaving)
        .listen('.chat.message', handleChatMessage);

    messageForm.addEventListener('submit', submitMessage);
    updateScrollPosition();

    async function submitMessage(event) {
        event.preventDefault();
        hideMessageError();

        const message = inputMessage.value.trim();
        if (!message) return;

        if (handleLocalThemeCommand(message)) {
            inputMessage.value = '';
            inputMessage.focus();
            return;
        }

        messageButton.disabled = true;

        try {
            await $wire.sendMessage(message);
            inputMessage.value = '';
            inputMessage.focus();
        } catch (error) {
            showMessageError(extractLivewireError(error));
        } finally {
            messageButton.disabled = false;
        }
    }

    function handleLocalThemeCommand(message) {
        const normalized = message.replace(/\s+/g, ' ').trim();

        if (normalized === 'دارک' || normalized === 'تم دارک') {
            document.documentElement.classList.add('dark');
            localStorage.setItem('okkio-theme', 'dark');
            appendMessage({ avatar: systemAvatar, display_name: 'سیستم اوکیوچت', uuid: 'system' }, 'تم شخصی شما روی حالت تیره قرار گرفت.', false);
            return true;
        }

        if (normalized === 'لایت' || normalized === 'تم لایت') {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('okkio-theme', 'light');
            appendMessage({ avatar: systemAvatar, display_name: 'سیستم اوکیوچت', uuid: 'system' }, 'تم شخصی شما روی حالت روشن قرار گرفت.', false);
            return true;
        }

        return false;
    }

    function handleChatMessage(event) {
        if (!event || !event.user || typeof event.message !== 'string') return;
        appendMessage(event.user, event.message, event.user.uuid === currentUserUuid);
    }

    function handleHereUsers(users) {
        usersList.replaceChildren();
        users.forEach(addUserToOnlineList);
        updateUsersCount();
    }

    function handleUserJoining(user) {
        appendMessage(user, 'وارد تالار شد!', false);
        addUserToOnlineList(user);
        updateUsersCount();
    }

    function handleUserLeaving(user) {
        appendMessage(user, 'از تالار خارج شد!', false);
        const element = document.getElementById(userElementId(user.uuid));
        if (element) element.remove();
        updateUsersCount();
    }

    function addUserToOnlineList(user) {
        if (!isSafeUser(user)) return;

        const id = userElementId(user.uuid);
        if (document.getElementById(id)) return;

        const item = document.createElement('li');
        item.id = id;
        item.className = 'flex items-center gap-4 truncate';

        item.appendChild(createAvatar(user.avatar, user.display_name));

        const details = document.createElement('div');
        details.className = 'text-sm truncate';

        const name = document.createElement('p');
        name.className = 'text-secondary-700 dark:text-secondary-300 truncate';
        name.textContent = user.uuid === currentUserUuid ? `${user.display_name} (شما)` : user.display_name;

        const status = document.createElement('p');
        status.className = 'text-secondary-500 dark:text-secondary-400 text-xs truncate';
        status.textContent = 'آنلاین';

        details.append(name, status);
        item.appendChild(details);
        usersList.appendChild(item);
    }

    function appendMessage(user, message, mine = false) {
        if (!isSafeUser(user) || typeof message !== 'string') return;

        const item = document.createElement('li');
        item.className = 'flex items-start gap-4';
        item.appendChild(createAvatar(user.avatar, user.display_name));

        const body = document.createElement('div');
        body.className = 'text-sm flex flex-col gap-1 min-w-0';

        const name = document.createElement('p');
        name.className = 'text-secondary-500 dark:text-secondary-400';
        name.textContent = mine ? 'شما' : user.display_name;

        const text = document.createElement('p');
        text.className = 'text-secondary-700 dark:text-secondary-300 message';
        text.textContent = message;

        body.append(name, text);
        item.appendChild(body);
        chatList.appendChild(item);
        updateScrollPosition();
    }

    function createAvatar(src, displayName) {
        const wrapper = document.createElement('div');
        wrapper.className = 'min-w-10 min-h-10 w-10 h-10 bg-secondary-100 dark:bg-secondary-800 rounded-md overflow-hidden';

        const image = document.createElement('img');
        image.className = 'w-full h-full object-center object-cover';
        image.alt = `آواتار ${displayName || 'کاربر'}`;
        image.src = isSafeAvatar(src) ? src : currentUserAvatar;

        wrapper.appendChild(image);
        return wrapper;
    }

    function isSafeAvatar(value) {
        return typeof value === 'string' && value.startsWith('data:image/svg+xml;base64,') && value.length < 20000;
    }

    function isSafeUser(user) {
        if (!user || typeof user.uuid !== 'string' || typeof user.display_name !== 'string') return false;

        const uuidIsValid = /^[0-9a-f-]{36}$/i.test(user.uuid) || user.uuid === 'system';
        return uuidIsValid && user.display_name.length <= 32 && isSafeAvatar(user.avatar);
    }

    function userElementId(uuid) {
        return `online-user-wrapper-${String(uuid).replace(/[^0-9a-z-]/gi, '')}`;
    }

    function updateUsersCount() {
        usersCount.textContent = String(usersList.children.length);
    }

    function updateScrollPosition() {
        chatListWrapper.scrollTop = chatListWrapper.scrollHeight;
    }

    function showMessageError(message) {
        messageError.textContent = message;
        messageError.classList.remove('hidden');
    }

    function hideMessageError() {
        messageError.textContent = '';
        messageError.classList.add('hidden');
    }

    function extractLivewireError(error) {
        const text = error?.message || '';
        if (text.includes('۵۰۰')) return 'حداکثر طول پیام ۵۰۰ نویسه است.';
        return 'ارسال پیام انجام نشد. چند ثانیه بعد دوباره تلاش کن.';
    }
</script>
@endscript
