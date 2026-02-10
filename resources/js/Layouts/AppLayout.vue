<template>
    <div class="min-h-screen bg-white">
        <div class="flex">
            <aside class="w-64 bg-gray-100 shadow-sm min-h-screen">
                <div class="p-6">
                    <div class="flex items-center mb-8">
                        <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                        <span class="text-xl font-bold text-gray-900">Daily Grow</span>
                    </div>
                    
                    <div class="text-sm text-gray-600 mb-6">
                        Название аккаунта
                    </div>

                    <nav class="space-y-2">
                        <Link
                            href="/reviews"
                            :class="[
                                'flex items-center px-4 py-3 text-gray-700 rounded-lg transition-colors',
                                $page.url === '/reviews' ? 'bg-gray-100' : 'hover:bg-gray-50'
                            ]"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Отзывы
                        </Link>
                        
                        <Link
                            href="/settings"
                            :class="[
                                'flex items-center px-4 py-3 text-gray-700 rounded-lg transition-colors',
                                $page.url === '/settings' ? 'bg-gray-100' : 'hover:bg-gray-50'
                            ]"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Настройка
                        </Link>
                    </nav>
                </div>
            </aside>

            <main class="flex-1 bg-white">
                <header class="bg-white shadow-sm border-b border-gray-200">
                    <div class="px-6 py-4 flex items-center justify-between">
                        <div class="flex-1">
                            <slot name="header"></slot>
                        </div>
                        <button
                            @click="logout"
                            class="p-2 text-gray-500 hover:text-gray-700 ml-auto"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </div>
                </header>

                <div class="p-6 bg-white">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';

const logout = () => {
    router.post('/logout');
};
</script>
