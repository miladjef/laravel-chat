<div class="min-h-svh grid grid-cols-1 lg:grid-cols-12 p-3 sm:p-4 bg-gray-100 gap-3 sm:gap-4 dark:bg-secondary-950">
    <aside class="lg:col-span-4 xl:col-span-3 flex flex-col gap-3 sm:gap-4 min-h-0">
        <div class="bg-white p-4 ring-1 ring-secondary-200 rounded-md dark:bg-secondary-900 dark:ring-secondary-800">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 min-w-10 min-h-10 rounded-md bg-primary-700 flex items-center justify-center text-white font-bold select-none"
                     data-avatar-seed="{{ auth()->user()->uuid }}">
                    {{ mb_substr(auth()->user()->display_name, 0, 1) }}
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

        <div class="bg-white px-4 py-3 ring-1 ring-secondary-200 rounded-md dark:bg-secondary-900 dark:ring-secondary-800">
            <div class="flex items-center justify-between gap-3 text-xs">
                <span class="text-secondary-500 dark:text-secondary-400">ارتباط لحظه‌ای</span>
                <span id="realtime-status" class="font-medium text-amber-600 dark:text-amber-400" role="status" aria-live="polite">
                    در حال اتصال
                </span>
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
            Programmer: Milad Jafari Gavzan
        </p>
    </aside>

    <main class="lg:col-span-8 xl:col-span-9 flex flex-col items-center justify-between gap-3 sm:gap-4 min-h-[65svh] lg:min-h-0">
        <div id="chat-list-wrapper" wire:ignore
             class="flex-auto min-h-0 overflow-y-auto w-full scroll rounded-md ring-1 ring-secondary-200 p-4 bg-white dark:bg-secondary-900 dark:ring-secondary-800">
            <ul class="flex flex-col gap-4" id="chat-list">
                @foreach($this->systemMessages() as $systemMessage)
                    <li class="flex items-start gap-4">
                        <div class="min-w-10 min-h-10 w-10 h-10 rounded-md bg-primary-700 text-white flex items-center justify-center font-bold select-none">O</div>
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
                <button id="btn-message" type="submit" disabled class="bg-primary-600 dark:bg-primary-800 px-5 py-2 rounded-md text-white disabled:opacity-50 disabled:cursor-not-allowed">
                    ارسال پیام
                </button>
            </form>
            <p id="message-error" class="hidden text-sm text-rose-500 mt-2" role="alert" aria-live="assertive"></p>
        </div>
    </main>
</div>

@script
<script>
    window.__LarvelCLobbyCleanup?.();

    const chatListWrapper = document.getElementById('chat-list-wrapper');
    const chatList = document.getElementById('chat-list');
    const usersList = document.getElementById('online-users-list');
    const usersCount = document.getElementById('online-users-count');
    const inputMessage = document.getElementById('input-message');
    const messageForm = document.getElementById('form-message');
    const messageButton = document.getElementById('btn-message');
    const messageError = document.getElementById('message-error');
    const realtimeStatus = document.getElementById('realtime-status');
    const currentUserUuid = @js(auth()->user()->uuid);
    const initialHistory = @js($this->recentMessages());
    const maxDomMessages = Math.max(50, Number(@js(config('chat.dom_message_limit', 300))));
    const heartbeatSeconds = Math.max(60, Number(@js(config('chat.heartbeat_seconds', 180))));
    const seenMessageIds = new Set();
    const seenMessageQueue = [];
    const dynamicMessageQueue = [];
    let realtimeConnected = false;
    let sendingMessage = false;
    let heartbeatTimer = null;

    applyAvatarColors();

    const savedTheme = localStorage.getItem('LarvelC-theme');
    if (savedTheme === 'light') document.documentElement.classList.remove('dark');
    if (savedTheme === 'dark') document.documentElement.classList.add('dark');

    initialHistory.forEach(handleChatPayload);

    const lobbyChannel = Echo.join('lobby')
        .here(handleHereUsers)
        .joining(handleUserJoining)
        .leaving(handleUserLeaving)
        .listen('.chat.message', handleChatMessage)
        .error(() => {
            updateRealtimeState('failed');
            showMessageError('احراز ارتباط لحظه‌ای انجام نشد. صفحه را تازه‌سازی کن.');
        });

    const realtimeConnection = Echo.connector?.pusher?.connection;
    const stateChangeHandler = ({ current }) => updateRealtimeState(current);
    const connectionErrorHandler = () => updateRealtimeState('unavailable');

    if (realtimeConnection) {
        updateRealtimeState(realtimeConnection.state || 'connecting');
        realtimeConnection.bind('state_change', stateChangeHandler);
        realtimeConnection.bind('error', connectionErrorHandler);
    } else {
        updateRealtimeState('unavailable');
    }

    messageForm.addEventListener('submit', submitMessage);
    startHeartbeat();
    updateScrollPosition();

    window.__LarvelCLobbyCleanup = () => {
        if (heartbeatTimer) window.clearInterval(heartbeatTimer);
        messageForm?.removeEventListener('submit', submitMessage);
        realtimeConnection?.unbind('state_change', stateChangeHandler);
        realtimeConnection?.unbind('error', connectionErrorHandler);
        try { Echo.leave('lobby'); } catch (_) {}
        window.__LarvelCLobbyCleanup = null;
    };

    document.addEventListener('livewire:navigating', () => window.__LarvelCLobbyCleanup?.(), { once: true });

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

        if (!realtimeConnected) {
            showMessageError('ارتباط لحظه‌ای برقرار نیست. پس از اتصال دوباره پیام را ارسال کن.');
            return;
        }

        sendingMessage = true;
        syncSendButton();

        try {
            const payload = await $wire.sendMessage(message);
            handleChatPayload(payload);
            inputMessage.value = '';
            inputMessage.focus();
        } catch (error) {
            showMessageError(extractLivewireError(error));
        } finally {
            sendingMessage = false;
            syncSendButton();
        }
    }

    function startHeartbeat() {
        const beat = async () => {
            try {
                await $wire.heartbeat();
            } catch (error) {
                if (Number(error?.status || error?.response?.status || 0) === 401) {
                    showMessageError('نشست ورود پایان یافته است. صفحه را تازه‌سازی کن.');
                }
            }
        };

        beat();
        heartbeatTimer = window.setInterval(beat, heartbeatSeconds * 1000);
    }

    function handleLocalThemeCommand(message) {
        const normalized = message.replace(/\s+/g, ' ').trim();
        const systemUser = { uuid: 'system', display_name: 'سیستم اوکیوچت' };

        if (normalized === 'دارک' || normalized === 'تم دارک') {
            document.documentElement.classList.add('dark');
            localStorage.setItem('LarvelC-theme', 'dark');
            appendMessage(systemUser, 'تم شخصی شما روی حالت تیره قرار گرفت.', false, new Date().toISOString());
            return true;
        }

        if (normalized === 'لایت' || normalized === 'تم لایت') {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('LarvelC-theme', 'light');
            appendMessage(systemUser, 'تم شخصی شما روی حالت روشن قرار گرفت.', false, new Date().toISOString());
            return true;
        }

        return false;
    }

    function handleChatMessage(payload) {
        handleChatPayload(payload);
    }

    function handleChatPayload(payload) {
        if (!isSafeChatPayload(payload) || seenMessageIds.has(payload.message_id)) return;

        rememberMessageId(payload.message_id);
        appendMessage(payload.user, payload.message, payload.user.uuid === currentUserUuid, payload.sent_at);
    }

    function handleHereUsers(users) {
        usersList.replaceChildren();
        users.forEach(addUserToOnlineList);
        updateUsersCount();
    }

    function handleUserJoining(user) {
        appendMessage(user, 'وارد تالار شد!', false, new Date().toISOString());
        addUserToOnlineList(user);
        updateUsersCount();
    }

    function handleUserLeaving(user) {
        if (!isSafeUser(user)) return;
        appendMessage(user, 'از تالار خارج شد!', false, new Date().toISOString());
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
        item.appendChild(createAvatar(user.uuid, user.display_name));

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

    function appendMessage(user, message, mine = false, sentAt = null) {
        if (!isSafeUser(user) || typeof message !== 'string') return;

        const item = document.createElement('li');
        item.dataset.dynamicMessage = '1';
        item.className = 'flex items-start gap-4';
        item.appendChild(createAvatar(user.uuid, user.display_name));

        const body = document.createElement('div');
        body.className = 'text-sm flex flex-col gap-1 min-w-0';

        const header = document.createElement('div');
        header.className = 'flex items-center gap-2 flex-wrap';

        const name = document.createElement('p');
        name.className = 'text-secondary-500 dark:text-secondary-400';
        name.textContent = mine ? 'شما' : user.display_name;

        const time = document.createElement('time');
        time.className = 'text-[10px] text-secondary-400 dark:text-secondary-500';
        time.dateTime = typeof sentAt === 'string' ? sentAt : '';
        time.textContent = formatMessageTime(sentAt);

        const text = document.createElement('p');
        text.className = 'text-secondary-700 dark:text-secondary-300 message';
        text.textContent = message;

        header.append(name, time);
        body.append(header, text);
        item.appendChild(body);
        chatList.appendChild(item);
        dynamicMessageQueue.push(item);
        trimMessageDom();
        updateScrollPosition();
    }

    function createAvatar(seed, displayName) {
        const avatar = document.createElement('div');
        avatar.className = 'min-w-10 min-h-10 w-10 h-10 rounded-md text-white flex items-center justify-center font-bold select-none';
        avatar.style.backgroundColor = avatarColor(seed);
        avatar.textContent = Array.from(displayName || '?')[0] || '?';
        avatar.setAttribute('aria-label', `نشان ${displayName || 'کاربر'}`);
        return avatar;
    }

    function avatarColor(seed) {
        let hash = 2166136261;
        for (const char of String(seed || '')) {
            hash ^= char.codePointAt(0);
            hash = Math.imul(hash, 16777619);
        }
        return `hsl(${Math.abs(hash) % 360} 55% 38%)`;
    }

    function applyAvatarColors() {
        document.querySelectorAll('[data-avatar-seed]').forEach((element) => {
            element.style.backgroundColor = avatarColor(element.dataset.avatarSeed);
        });
    }

    function isSafeUser(user) {
        if (!user || typeof user.uuid !== 'string' || typeof user.display_name !== 'string') return false;
        const uuidIsValid = /^[0-9a-f-]{36}$/i.test(user.uuid) || user.uuid === 'system';
        return uuidIsValid && user.display_name.length <= 32;
    }

    function isSafeChatPayload(payload) {
        return payload
            && typeof payload.message_id === 'string'
            && /^[0-9a-f-]{36}$/i.test(payload.message_id)
            && typeof payload.message === 'string'
            && payload.message.length <= 500
            && typeof payload.sent_at === 'string'
            && isSafeUser(payload.user);
    }

    function rememberMessageId(messageId) {
        seenMessageIds.add(messageId);
        seenMessageQueue.push(messageId);

        if (seenMessageQueue.length > 600) {
            const expiredId = seenMessageQueue.shift();
            seenMessageIds.delete(expiredId);
        }
    }

    function trimMessageDom() {
        while (dynamicMessageQueue.length > maxDomMessages) {
            dynamicMessageQueue.shift()?.remove();
        }
    }

    function formatMessageTime(value) {
        const date = new Date(value || Date.now());
        if (Number.isNaN(date.getTime())) return '';

        return new Intl.DateTimeFormat('fa-IR', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
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

    function updateRealtimeState(state) {
        const normalized = String(state || '').toLowerCase();
        realtimeConnected = normalized === 'connected';

        const labels = {
            initialized: 'آماده اتصال',
            connecting: 'در حال اتصال',
            connected: 'متصل',
            unavailable: 'ارتباط در دسترس نیست',
            failed: 'خطای ارتباط',
            disconnected: 'قطع شده',
        };

        realtimeStatus.textContent = labels[normalized] || 'در حال اتصال';
        realtimeStatus.className = realtimeConnected
            ? 'font-medium text-emerald-600 dark:text-emerald-400'
            : 'font-medium text-amber-600 dark:text-amber-400';

        syncSendButton();
    }

    function syncSendButton() {
        messageButton.disabled = sendingMessage || !realtimeConnected;
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
        const status = Number(error?.status || error?.response?.status || 0);
        const validationMessage = error?.response?.data?.errors?.message?.[0];
        const text = String(validationMessage || error?.response?.data?.message || error?.message || '');

        if (status === 401) return 'نشست ورود معتبر نیست. صفحه را تازه‌سازی کن.';
        if (status === 419) return 'نشست صفحه منقضی شده است. صفحه را تازه‌سازی کن.';
        if (status === 429 || text.includes('تعداد پیام')) return 'تعداد پیام‌ها زیاد است. چند ثانیه بعد دوباره ارسال کن.';
        if (status === 422 && validationMessage) return validationMessage;
        if (text.includes('۵۰۰') || text.includes('500')) return 'حداکثر طول پیام ۵۰۰ نویسه است.';
        if (status >= 500) return 'سرویس ارسال پیام در دسترس نیست. دوباره تلاش کن.';

        return 'ارسال پیام انجام نشد. اتصال را بررسی و دوباره تلاش کن.';
    }
</script>
@endscript
