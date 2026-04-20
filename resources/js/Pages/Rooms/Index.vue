<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

const props = defineProps({
    rooms: {
        type: Array,
        default: () => []
    },
    ownedRooms: {
        type: Array,
        default: () => []
    },
    publicRooms: {
        type: Array,
        default: () => []
    },
    activeTab: String,
    ownedRoomsCount: {
        type: Number,
        default: 0
    },
    joinedRoomsCount: {
        type: Number,
        default: 0
    },
    publicRoomsCount: {
        type: Number,
        default: 0
    },
});

const currentTab = computed(() => props.activeTab || 'dashboard');


const form = useForm({
    name: '',
    is_public: true,
    password: '',
});

const joinRoomId = ref('');
const showPasswordModal = ref(false);
const joinForm = useForm({
    password: '',
});

const createRoom = () => {
    form.post(route('rooms.store'), {
        onSuccess: () => form.reset(),
    });
};

const initiateJoin = async (id) => {
    if (!id) return;
    try {
        const response = await axios.get(route('rooms.check-private', id));
        if (response.data.requires_password) {
            joinRoomId.value = id;
            joinForm.password = '';
            showPasswordModal.value = true;
        } else {
            joinForm.post(route('rooms.join', id));
        }
    } catch (e) {
        if (e.response && e.response.status === 404) {
            alert('Không tìm thấy phòng vẽ với mã này.');
        } else {
            alert('Có lỗi xảy ra, vui lòng thử lại.');
        }
    }
};

const joinRoom = () => initiateJoin(joinRoomId.value);

const submitJoinWithPassword = () => {
    joinForm.post(route('rooms.join', joinRoomId.value), {
        onSuccess: () => {
            showPasswordModal.value = false;
        }
    });
};
</script>

<template>
    <Head title="Phòng vẽ của bạn" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ 
                    currentTab === 'my-rooms' ? 'Phòng vẽ của tôi' : 
                    currentTab === 'public-rooms' ? 'Khám phá cộng đồng' : 
                    'Tổng quan Dashboard' 
                }}
            </h2>
        </template>

        <div class="py-6">
            <!-- Alert Messages -->
            <div v-if="$page.props.flash?.error" class="mb-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ $page.props.flash.error }}</span>
                </div>
            </div>

            <!-- DASHBOARD VIEW -->
            <div v-if="currentTab === 'dashboard'" class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-3xl mb-2">🎨</div>
                        <div class="text-2xl font-bold dark:text-white">{{ ownedRoomsCount || 0 }}</div>
                        <div class="text-sm text-gray-500">Phòng bạn sở hữu</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-3xl mb-2">🤝</div>
                        <div class="text-2xl font-bold dark:text-white">{{ joinedRoomsCount || 0 }}</div>
                        <div class="text-sm text-gray-500">Phòng đã tham gia</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-3xl mb-2">🌐</div>
                        <div class="text-2xl font-bold dark:text-white">{{ publicRoomsCount || 0 }}</div>
                        <div class="text-sm text-gray-500">Phòng public mới</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Create Room -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold mb-4 dark:text-white">🚀 Tạo phòng nhanh</h3>
                        <form @submit.prevent="createRoom" class="space-y-4">
                            <div>
                                <InputLabel for="name" value="Tên phòng" />
                                <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>
                            <div class="flex items-center">
                                <input id="is_public" type="checkbox" class="rounded text-indigo-600 focus:ring-indigo-500" v-model="form.is_public" :true-value="false" :false-value="true">
                                <label for="is_public" class="ml-2 text-sm text-gray-600 dark:text-gray-400">Đặt làm phòng riêng tư (Private)</label>
                            </div>
                            <div v-if="!form.is_public">
                                <InputLabel for="password" value="Mật khẩu bảo vệ (Bắt buộc)" />
                                <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" placeholder="Nhập mật khẩu..." :required="!form.is_public" />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>
                            <PrimaryButton :disabled="form.processing">Bắt đầu vẽ ngay</PrimaryButton>
                        </form>
                    </div>

                    <!-- Join Room -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold mb-4 dark:text-white">🔑 Tham gia bằng ID</h3>
                        <p class="text-sm text-gray-500 mb-4">Nhập mã định danh phòng (UUID) để vào vẽ cùng bạn bè.</p>
                        <div class="flex gap-2">
                            <TextInput id="join_id" type="text" class="flex-grow" v-model="joinRoomId" placeholder="Mã ID phòng..." />
                            <PrimaryButton @click="joinRoom" :disabled="!joinRoomId">Vào</PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MY ROOMS VIEW -->
            <div v-else-if="currentTab === 'my-rooms'" class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                    <h3 class="text-lg font-bold mb-6 dark:text-white">Danh sách phòng của bạn</h3>
                    
                    <div v-if="rooms.length === 0 && ownedRooms.length === 0" class="text-center py-12 text-gray-500">
                        <div class="text-5xl mb-4">🏜️</div>
                        <p>Bạn chưa có phòng vẽ nào.</p>
                        <Link :href="route('dashboard')" class="text-indigo-600 hover:underline mt-2 inline-block">Về trang chủ để tạo phòng</Link>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="room in ownedRooms" :key="room.id" class="group bg-gray-50 dark:bg-gray-900/50 p-5 rounded-xl border border-transparent hover:border-indigo-300 dark:hover:border-indigo-700 transition duration-200">
                            <div class="flex justify-between items-start mb-4">
                                <h4 class="font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 transition">{{ room.name }}</h4>
                                <span class="bg-indigo-100 text-indigo-700 text-[10px] uppercase font-bold px-2 py-0.5 rounded">Tôi làm chủ</span>
                            </div>
                            <div class="flex items-center justify-between mt-auto">
                                <span class="text-xs" :class="room.is_public ? 'text-green-500' : 'text-amber-500'">
                                    {{ room.is_public ? '🌐 Công khai' : '🔒 Riêng tư' }}
                                </span>
                                <Link :href="route('rooms.show', room.id)" class="text-sm font-bold text-indigo-600 hover:text-indigo-800">Vào vẽ ➔</Link>
                            </div>
                        </div>

                        <div v-for="room in rooms" :key="room.id" class="bg-white dark:bg-gray-900 p-5 rounded-xl border border-gray-100 dark:border-gray-700">
                            <h4 class="font-bold text-gray-900 dark:text-white">{{ room.name }}</h4>
                            <p class="text-xs text-gray-500 mb-4">Chủ: {{ room.owner.name }}</p>
                            <Link :href="route('rooms.show', room.id)" class="text-sm font-bold text-indigo-600 hover:opacity-80">Tiếp tục ➔</Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PUBLIC ROOMS VIEW -->
            <div v-else-if="currentTab === 'public-rooms'" class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                    <header class="mb-8">
                        <h3 class="text-xl font-bold dark:text-white">🌐 Khám phá thế giới</h3>
                        <p class="text-sm text-gray-500">Các phòng vẽ công khai đang diễn ra.</p>
                    </header>
                    
                    <div v-if="!publicRooms || publicRooms.length === 0" class="text-center py-20 text-gray-500">
                        <div class="text-5xl mb-4">🌌</div>
                        <p>Hiện không có phòng công khai nào mới.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="room in publicRooms" :key="room.id" class="bg-white dark:bg-gray-900 border dark:border-gray-700 p-6 rounded-2xl hover:shadow-xl transition duration-300">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/40 rounded-full flex items-center justify-center text-2xl">🖌️</div>
                                <span class="text-[10px] font-bold px-2 py-1 rounded" :class="room.is_public ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                                    {{ room.is_public ? 'Công khai' : 'Có mật khẩu' }}
                                </span>
                            </div>
                            <h4 class="font-bold text-lg text-gray-900 dark:text-white mb-1 truncate">{{ room.name }}</h4>
                            <p class="text-xs text-gray-500 mb-6">Bởi: {{ room.owner.name }}</p>
                            <button @click="initiateJoin(room.id)" class="block w-full text-center py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-sm transition">Tham gia</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal nhập pass -->
        <Modal :show="showPasswordModal" @close="showPasswordModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Phòng vẽ Riêng Tư
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Chủ phòng đã yêu cầu mật khẩu để tham gia phòng vẽ này.
                </p>
                <div class="mt-6">
                    <InputLabel for="join_password" value="Mật khẩu" class="sr-only" />
                    <TextInput
                        id="join_password"
                        v-model="joinForm.password"
                        type="password"
                        class="block w-full"
                        placeholder="Nhập mật khẩu..."
                        @keyup.enter="submitJoinWithPassword"
                    />
                    <InputError :message="joinForm.errors.password" class="mt-2" />
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showPasswordModal = false">
                        Hủy
                    </SecondaryButton>
                    <PrimaryButton class="ml-3" @click="submitJoinWithPassword" :disabled="joinForm.processing">
                        Tham gia
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
