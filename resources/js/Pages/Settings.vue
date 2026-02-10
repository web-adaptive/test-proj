<template>
    <div class="min-h-screen bg-gray-50">
        <Layout>
            <div class="max-w-2xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Подключить Яндекс</h1>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-gray-700 mb-4">
                        Укажите ссылку на Яндекс, пример 
                        <span class="text-blue-600">https://yandex.ru/maps/org/samoye_populyarnoye_kafe/1010501395/reviews/</span>
                    </p>

                    <form @submit.prevent="submit">
                        <div class="mb-4">
                            <input
                                v-model="form.yandex_url"
                                type="url"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="https://yandex.ru/maps/org/..."
                            />
                        </div>


                        <button
                            type="submit"
                            class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Сохранить
                        </button>
                    </form>

                    <div v-if="$page.props.flash?.success" class="mt-4 text-green-600 text-sm">
                        {{ $page.props.flash.success }}
                    </div>
                    <div v-if="errors.yandex_url" class="mt-4 text-red-600 text-sm">
                        {{ errors.yandex_url }}
                    </div>
                </div>
            </div>
        </Layout>
    </div>
</template>

<script setup>
import Layout from '../Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    yandexUrl: {
        type: String,
        default: '',
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    success: {
        type: String,
        default: '',
    },
});

const form = useForm({
    yandex_url: props.yandexUrl || '',
});

const submit = () => {
    form.post('/settings');
};
</script>
