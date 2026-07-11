{{-- wire:poll is a fallback; the native:MessageReceived listener updates instantly --}}
<div class="flex h-screen bg-white" wire:poll.10s>
    <!-- Left Sidebar - Folders & Labels -->
    <div class="w-64 border-r border-gray-200 bg-white flex flex-col">
        <!-- Logo/Header -->
        <div class="p-4 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-blue-600">Mail</h1>
        </div>

        <!-- Compose Button -->
        <div class="p-4">
            <button
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                + Compose
            </button>
        </div>

        <!-- Folders -->
        <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
            <div class="text-xs font-semibold text-gray-500 uppercase px-3 py-2">Folders</div>

            <a href="#"
                class="flex items-center justify-between px-3 py-2 rounded-lg bg-blue-50 text-blue-600 font-medium">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                        </path>
                    </svg>
                    Inbox
                </span>
                <span class="bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full">12</span>
            </a>

            <a href="#"
                class="flex items-center justify-between px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Sent
                </span>
                <span class="text-gray-500 text-sm">24</span>
            </a>

            <a href="#"
                class="flex items-center justify-between px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                        </path>
                    </svg>
                    Drafts
                </span>
                <span class="text-gray-500 text-sm">3</span>
            </a>

            <a href="#"
                class="flex items-center justify-between px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9-4v4m0 0v4">
                        </path>
                    </svg>
                    Trash
                </span>
                <span class="text-gray-500 text-sm">5</span>
            </a>

            <a href="#"
                class="flex items-center justify-between px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Spam
                </span>
                <span class="text-gray-500 text-sm">8</span>
            </a>
        </nav>

        <!-- Labels -->
        <div class="px-2 py-4 border-t border-gray-200">
            <div class="text-xs font-semibold text-gray-500 uppercase px-3 py-2">Labels</div>
            <div class="space-y-2">
                <a href="#" class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                    <span class="w-3 h-3 rounded-full bg-red-500"></span>
                    <span class="text-sm">Important</span>
                </a>
                <a href="#" class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                    <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                    <span class="text-sm">Work</span>
                </a>
                <a href="#" class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                    <span class="text-sm">Personal</span>
                </a>
                <a href="#" class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                    <span class="w-3 h-3 rounded-full bg-green-500"></span>
                    <span class="text-sm">Follow Up</span>
                </a>
            </div>
        </div>

        <!-- Settings -->
        <div class="p-4 border-t border-gray-200">
            <button
                class="w-full flex items-center justify-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                    </path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Settings
            </button>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col">
        <!-- Top Header with Search -->
        <div class="border-b border-gray-200 bg-white p-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex-1 relative">
                    <input type="text" placeholder="Search emails..."
                        class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <div class="flex items-center gap-2">
                    <button class="p-2 hover:bg-gray-100 rounded-lg transition">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z">
                            </path>
                        </svg>
                    </button>
                    <button class="p-2 hover:bg-gray-100 rounded-lg transition">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z">
                            </path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Email List and Preview Container -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Email List -->
            <div class="w-96 border-r border-gray-200 bg-white overflow-y-auto">
                <!-- List Controls -->
                <div class="sticky top-0 bg-white border-b border-gray-200 p-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" class="w-4 h-4 rounded border-gray-300">
                        <span class="text-sm text-gray-600">Select all</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button class="p-1 hover:bg-gray-100 rounded transition" title="Archive">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9-4v4m0 0v4">
                                </path>
                            </svg>
                        </button>
                        <button class="p-1 hover:bg-gray-100 rounded transition" title="Delete">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                </path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Email Items -->
                @foreach ($emails as $email)
                    <div class="border-b border-gray-100 hover:bg-blue-50 cursor-pointer transition p-4 flex gap-3">
                        <input type="checkbox" class="w-4 h-4 rounded border-gray-300 mt-1 flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900 truncate">{{ $email->from ?? 'Unknown' }}</p>
                                    <p class="text-sm text-gray-700 truncate">{{ $email->subject ?? 'No Subject' }}</p>
                                    <p class="text-xs text-gray-500 truncate">
                                        {{ $email->body_text ? substr($email->body_text, 0, 50) . '...' : 'No preview' }}
                                    </p>
                                </div>
                                <button class="flex-shrink-0 p-1 hover:bg-yellow-100 rounded transition">
                                    <svg class="w-4 h-4 text-gray-400 hover:text-yellow-500" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center gap-1">
                                    @if ($email->attachments && count($email->attachments) > 0)
                                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z">
                                            </path>
                                        </svg>
                                    @endif
                                </div>
                                <span class="text-xs text-gray-500">{{ $email->received_at->format('M j') }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach

                <!-- Pagination -->
                <div class="p-4 border-t border-gray-200">
                    {{ $emails->links() }}
                </div>
            </div>

            <!-- Email Preview Pane -->
            <div class="flex-1 bg-white overflow-y-auto">
                @if ($selectedEmail)
                    <!-- Preview Header -->
                    <div class="sticky top-0 bg-white border-b border-gray-200 p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                                    {{ $selectedEmail->subject ?? 'No Subject' }}</h2>
                                <div class="flex items-center gap-3 mb-4">
                                    <div
                                        class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
                                        {{ substr($selectedEmail->from ?? 'U', 0, 1) }}
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900">{{ $selectedEmail->from ?? 'Unknown' }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            {{ $selectedEmail->received_at->format('M j, Y \a\t g:i A') }}</p>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p><span class="font-semibold">To:</span> {{ $selectedEmail->to ?? 'Unknown' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button class="p-2 hover:bg-gray-100 rounded-lg transition" title="Star">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                        </path>
                                    </svg>
                                </button>
                                <button class="p-2 hover:bg-gray-100 rounded-lg transition" title="Archive">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9-4v4m0 0v4">
                                        </path>
                                    </svg>
                                </button>
                                <button class="p-2 hover:bg-gray-100 rounded-lg transition" title="Delete">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                                <button class="p-2 hover:bg-gray-100 rounded-lg transition" title="More">
                                    <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Email Body -->
                    <div class="p-6">
                        <div class="prose prose-sm max-w-none">
                            @if ($selectedEmail->body_html)
                                {!! $selectedEmail->body_html !!}
                            @else
                                <p class="text-gray-700 whitespace-pre-wrap">{{ $selectedEmail->body_text }}</p>
                            @endif
                        </div>

                        <!-- Attachments -->
                        @if ($selectedEmail->attachments && count($selectedEmail->attachments) > 0)
                            <div class="mt-8 pt-6 border-t border-gray-200">
                                <h3 class="font-semibold text-gray-900 mb-4">Attachments
                                    ({{ count($selectedEmail->attachments) }})</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach ($selectedEmail->attachments as $att)
                                        <div
                                            class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                                <svg class="w-8 h-8 text-gray-400 flex-shrink-0" fill="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path
                                                        d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z">
                                                    </path>
                                                </svg>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 truncate">
                                                        {{ $att['name'] }}</p>
                                                    <p class="text-xs text-gray-500">
                                                        {{ number_format($att['size'] / 1024, 1) }} KB</p>
                                                </div>
                                            </div>
                                            <a href="#"
                                                class="ml-2 p-2 hover:bg-blue-50 rounded-lg transition flex-shrink-0">
                                                <svg class="w-5 h-5 text-blue-600" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                                    </path>
                                                </svg>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Reply Section -->
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <div class="flex gap-3">
                                <button
                                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    Reply
                                </button>
                                <button
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-900 font-semibold py-2 px-4 rounded-lg transition">
                                    Reply All
                                </button>
                                <button
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-900 font-semibold py-2 px-4 rounded-lg transition">
                                    Forward
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Empty State -->
                    <div class="h-full flex items-center justify-center">
                        <div class="text-center">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No email selected</h3>
                            <p class="text-gray-500">Select an email from the list to view its contents</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
