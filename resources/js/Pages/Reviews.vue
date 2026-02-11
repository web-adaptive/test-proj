<template>
    <div class="min-h-screen bg-white">
        <Layout>
            <div class="flex gap-6">
                <div class="flex-1">
                    <div v-if="$page.props.flash?.success" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        {{ $page.props.flash.success }}
                    </div>
                    <div v-if="$page.props.flash?.error" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        {{ $page.props.flash.error }}
                    </div>
                    
                    <div v-if="hasSettings" class="mb-6">
                        <button
                            @click="syncReviews"
                            :disabled="syncing"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed mb-2"
                        >
                            {{ syncing ? 'Синхронизация...' : 'Синхронизировать отзывы' }}
                        </button>
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-gray-700 font-medium">Яндекс Карты</span>
                        </div>
                    </div>
                    
                    <div v-if="!reviews.data || reviews.data.length === 0" class="text-center py-12">
                        <p class="text-gray-500 mb-4">Отзывы не найдены</p>
                        <button
                            v-if="hasSettings"
                            @click="syncReviews"
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                        >
                            Синхронизировать отзывы
                        </button>
                        <p v-else class="text-sm text-gray-400">
                            Сначала настройте ссылку на Яндекс в настройках
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="review in reviews.data"
                            :key="review.id"
                            class="bg-white rounded-lg shadow-md p-6"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <span class="text-sm text-gray-500">
                                        {{ formatDate(review.date) }}
                                    </span>
                                    <div class="flex items-center">
                                        <span class="text-sm text-gray-600 mr-1">{{ review.branch || 'Филиал 1' }}</span>
                                        <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex">
                                    <svg
                                        v-for="i in 5"
                                        :key="i"
                                        class="w-5 h-5"
                                        :class="i <= review.rating ? 'text-yellow-400' : 'text-gray-300'"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                </div>
                            </div>

                            <div class="mb-3 text-sm text-gray-700">
                                <span class="font-medium">{{ review.reviewer_name }}</span>
                                <span v-if="review.reviewer_phone" class="text-gray-500 ml-2">
                                    {{ review.reviewer_phone }}
                                </span>
                            </div>

                            <p class="text-gray-700 text-sm leading-relaxed">
                                {{ review.text }}
                            </p>
                        </div>
                        
                        <div v-if="reviews.links && reviews.links.length > 3" class="mt-8 flex justify-center items-center">
                            <nav class="flex items-center space-x-1">
                                <button
                                    v-for="link in reviews.links"
                                    :key="link.label"
                                    @click="goToPage(link.url)"
                                    :disabled="!link.url"
                                    v-html="link.label"
                                    :class="[
                                        'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                                        link.active 
                                            ? 'bg-blue-600 text-white cursor-default' 
                                            : link.url 
                                                ? 'bg-white text-gray-700 border border-gray-300 hover:bg-blue-50 hover:border-blue-300 cursor-pointer' 
                                                : 'bg-gray-100 text-gray-400 cursor-not-allowed opacity-50'
                                    ]"
                                ></button>
                            </nav>
                            <div class="ml-4 text-sm text-gray-600">
                                Показано {{ reviews.from || 0 }} - {{ reviews.to || 0 }} из {{ reviews.total || 0 }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-80">
                    <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-gray-900 mb-2">
                                {{ displayRating.toFixed(1) }}
                            </div>
                            <div class="flex justify-center mb-3">
                                <div class="flex">
                                    <svg
                                        v-for="i in 5"
                                        :key="i"
                                        class="w-6 h-6"
                                        :class="i <= Math.floor(displayRating) ? 'text-yellow-400' : (i === Math.ceil(displayRating) && displayRating % 1 >= 0.5 ? 'text-yellow-400' : 'text-gray-300')"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="text-sm text-gray-600">
                                Всего отзывов: {{ totalReviews.toLocaleString('ru-RU') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Layout>
    </div>
</template>

<script setup>
import Layout from '../Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    reviews: Object,
    totalReviews: Number,
    averageRating: Number,
    hasSettings: Boolean,
});

const syncing = ref(false);

// Ограничиваем отображаемый рейтинг максимумом 5.0
const displayRating = computed(() => {
    return Math.min(props.averageRating || 0, 5.0);
});

const formatDate = (date) => {
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${minutes}`;
};

const syncReviews = () => {
    syncing.value = true;
    router.post('/reviews/sync', {}, {
        preserveScroll: true,
        onFinish: () => {
            syncing.value = false;
        },
    });
};

const goToPage = (url) => {
    if (url) {
        router.get(url, {}, {
            preserveScroll: true,
            preserveState: true,
        });
    }
};
</script>
