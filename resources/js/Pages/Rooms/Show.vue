<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, useForm, router } from '@inertiajs/vue3';
import { onMounted, ref, reactive, computed, watch } from 'vue';
// ... (imports remain the same)
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    room: Object,
    canvasObjects: Array,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isSettingsOpen = ref(false);
const isMembersOpen = ref(false);

const form = useForm({
    name: props.room.name,
    is_public: props.room.is_public,
    password: '',
});

// Cập nhật lại form khi dữ liệu room từ server thay đổi (sau khi patch thành công)
watch(() => props.room, (newRoom) => {
    form.name = newRoom.name;
    form.is_public = !!newRoom.is_public;
    form.password = '';
}, { deep: true });

const submitSettings = () => {
    form.patch(route('rooms.update', props.room.id), {
        onSuccess: () => {
            isSettingsOpen.value = false;
        },
    });
};

const manageMembers = () => {
    isMembersOpen.value = true;
};

const kickMember = (id) => {
    if (confirm('Bạn có chắc chắn muốn đuổi thành viên này ra khỏi phòng vẽ?')) {
        router.delete(route('rooms.members.kick', { room: props.room.id, user: id }), {
            preserveScroll: true,
            onSuccess: () => {
                 router.reload({ only: ['room'], preserveScroll: true });
            }
        });
    }
};

const leaveRoom = () => {
    if (confirm('Bạn có chắc chắn muốn rời khỏi phòng vẽ?')) {
        router.delete(route('rooms.leave', props.room.id));
    }
};

const deleteRoom = () => {
    if (confirm('BẠN CÓ CHẮC CHẮN MUỐN XÓA PHÒNG NÀY? Toàn bộ dữ liệu vẽ sẽ bị xóa vĩnh viễn và không thể khôi phục.')) {
        form.delete(route('rooms.destroy', props.room.id));
    }
};

// ... (existing canvas refs)
const stageConfig = reactive({
    width: window.innerWidth * 0.9,
    height: window.innerHeight * 0.7,
});

const objects = ref(props.canvasObjects || []);
const selectedTool = ref('pencil');
const isDrawing = ref(false);
const currentId = ref(null);
const copyStatus = ref('🤝 Mời bạn bè');

const redoStack = ref([]);

const canUndo = computed(() => objects.value.some(obj => obj.user_id === user.value.id));

const undo = async () => {
    for (let i = objects.value.length - 1; i >= 0; i--) {
        if (objects.value[i].user_id === user.value.id) {
            const obj = objects.value[i];
            objects.value.splice(i, 1);
            redoStack.value.push(obj);
            try {
                await axios.delete(route('rooms.objects.destroy', { room: props.room.id, object: obj.id }));
            } catch (err) {
                console.error('Lỗi khi undo:', err);
            }
            break;
        }
    }
};

const redo = async () => {
    if (redoStack.value.length === 0) return;
    const obj = redoStack.value.pop();
    objects.value.push(obj);
    try {
        await axios.post(route('rooms.objects.store', props.room.id), {
            id: obj.id,
            type: obj.type,
            data: obj.data,
            z_index: obj.z_index
        });
    } catch (err) {
        console.error('Lỗi khi redo:', err);
    }
};

const copyInviteLink = () => {
    navigator.clipboard.writeText(props.room.id);
    copyStatus.value = '✅ Đã copy mã ID!';
    setTimeout(() => {
        copyStatus.value = '🤝 Mời bạn bè';
    }, 2000);
};

const handleMouseDown = (e) => {
    isDrawing.value = true;
    const pos = e.target.getStage().getPointerPosition();
    const id = crypto.randomUUID();
    currentId.value = id;

    let newObject = {
        id: id,
        user_id: user.value.id,
        type: selectedTool.value,
        data: {},
        z_index: objects.value.length
    };

    if (selectedTool.value === 'pencil' || selectedTool.value === 'eraser') {
        newObject.data = {
            points: [pos.x, pos.y],
            stroke: selectedTool.value === 'eraser' ? '#ffffff' : '#df4b26',
            strokeWidth: selectedTool.value === 'eraser' ? 20 : 5,
            lineCap: 'round',
            lineJoin: 'round',
            globalCompositeOperation: selectedTool.value === 'eraser' ? 'destination-out' : 'source-over',
        };
    } else if (selectedTool.value === 'rect') {
        newObject.data = {
            x: pos.x,
            y: pos.y,
            width: 0,
            height: 0,
            fill: 'transparent',
            stroke: '#df4b26',
            strokeWidth: 2,
        };
    } else if (selectedTool.value === 'circle') {
        newObject.data = {
            x: pos.x,
            y: pos.y,
            radius: 0,
            fill: 'transparent',
            stroke: '#df4b26',
            strokeWidth: 2,
        };
    }

    objects.value.push(newObject);
};

const handleMouseMove = (e) => {
    if (!isDrawing.value) return;

    const stage = e.target.getStage();
    const point = stage.getPointerPosition();
    const lastObject = objects.value[objects.value.length - 1];

    if (selectedTool.value === 'pencil' || selectedTool.value === 'eraser') {
        lastObject.data.points = lastObject.data.points.concat([point.x, point.y]);
    } else if (selectedTool.value === 'rect') {
        lastObject.data.width = point.x - lastObject.data.x;
        lastObject.data.height = point.y - lastObject.data.y;
    } else if (selectedTool.value === 'circle') {
        const dx = point.x - lastObject.data.x;
        const dy = point.y - lastObject.data.y;
        lastObject.data.radius = Math.sqrt(dx * dx + dy * dy);
    }
};

const handleMouseUp = async () => {
    if (!isDrawing.value) return;
    isDrawing.value = false;

    // Reset redo stack when a new stroke is manually drawn
    redoStack.value = [];

    const lastObject = objects.value[objects.value.length - 1];
    
    try {
        await axios.post(route('rooms.objects.store', props.room.id), {
            id: lastObject.id,
            type: lastObject.type,
            data: lastObject.data,
            z_index: lastObject.z_index
        });
    } catch (err) {
        console.error('Lỗi khi lưu nét vẽ:', err);
    }
};

const handleDragEnd = async (e, obj) => {
    // Only update if it was actually moved
    const stage = e.target.getStage();
    const pos = e.target.position();
    
    obj.data.x = pos.x;
    obj.data.y = pos.y;

    try {
        await axios.patch(route('rooms.objects.update', { room: props.room.id, object: obj.id }), {
            data: obj.data
        });
    } catch (err) {
        console.error('Lỗi khi cập nhật vị trí:', err);
    }
};

const clearMyDrawings = async () => {
    if (!confirm('Bạn có chắc chắn muốn dọn sạch các nét vẽ của mình?')) return;
    
    try {
        await axios.delete(route('rooms.clear-mine', props.room.id));
        // Local update
        objects.value = objects.value.filter(obj => obj.user_id != user.value.id);
        redoStack.value = [];
    } catch (err) {
        console.error('Lỗi khi xóa nét vẽ cá nhân:', err);
    }
};

const clearAll = async () => {
    const isOwner = props.room.owner_id == user.value.id;
    
    if (isOwner) {
        if (!confirm('BẠN LÀ CHỦ PHÒNG. Bạn có chắc muốn xóa SẠCH TOÀN BỘ bảng vẽ của tất cả mọi người?')) return;
        try {
            await axios.delete(route('rooms.clear', props.room.id));
            objects.value = [];
            redoStack.value = [];
        } catch (err) {
            console.error('Lỗi khi xóa toàn bộ bảng:', err);
        }
    } else {
        if (!confirm('Bạn muốn gửi yêu cầu xóa sạch bảng tới Chủ phòng?')) return;
        try {
            await axios.post(route('rooms.request-clear', props.room.id));
            alert('Đã gửi yêu cầu xóa bảng tới mọi người.');
        } catch (err) {
            console.error('Lỗi khi gửi yêu cầu xóa:', err);
        }
    }
};

const handleClearAllRequest = async (e) => {
    // Only owner can actually perform the clear all
    const isOwner = props.room.owner_id == user.value.id;
    
    if (isOwner && e.requesterId != user.value.id) {
        if (confirm(`Người dùng "${e.requesterName}" yêu cầu XÓA SẠCH toàn bộ bảng. Bạn có đồng ý không?`)) {
            try {
                await axios.delete(route('rooms.clear', props.room.id));
                objects.value = [];
            } catch (err) {
                console.error('Lỗi khi thực hiện xóa toàn bộ theo yêu cầu:', err);
            }
        }
    } else if (!isOwner) {
        // Just a notification for others
        console.log(`Thông báo: ${e.requesterName} đã gửi yêu cầu xóa bảng.`);
    }
};

onMounted(() => {
    window.addEventListener('resize', () => {
        stageConfig.width = window.innerWidth * 0.9;
        stageConfig.height = window.innerHeight * 0.7;
    });

    // Join the room channel
    window.Echo.private(`rooms.${props.room.id}`)
        .listen('CanvasObjectCreated', (e) => {
            console.log('Nhận diện đối tượng mới:', e.object);
            // Check if object already exists (to avoid duplicates from self)
            const exists = objects.value.find(obj => obj.id === e.object.id);
            if (!exists) {
                objects.value.push(e.object);
            }
        })
        .listen('CanvasObjectUpdated', (e) => {
            console.log('Cập nhật đối tượng:', e.object);
            const index = objects.value.findIndex(obj => obj.id === e.object.id);
            if (index !== -1) {
                objects.value[index].data = e.object.data;
            }
        })
        .listen('CanvasCleared', (e) => {
            objects.value = [];
            redoStack.value = [];
            alert('Bảng vẽ đã được dọn sạch.');
        })
        .listen('CanvasObjectsDeleted', (e) => {
            objects.value = objects.value.filter(obj => obj.user_id !== e.userId);
            if (e.userId === user.value.id) {
                redoStack.value = [];
            }
        })
        .listen('CanvasObjectDeleted', (e) => {
            const index = objects.value.findIndex(obj => obj.id === e.objectId);
            if (index !== -1) {
                objects.value.splice(index, 1);
            }
        })
        .listen('ClearAllRequested', (e) => {
            handleClearAllRequest(e);
        })
        .listen('UserRemovedFromRoom', (e) => {
            if (e.userId === user.value.id) {
                alert(e.message);
                router.visit(route('rooms.index'));
            } else {
                router.reload({ only: ['room'], preserveScroll: true });
            }
        });
});
</script>

<template>
    <Head :title="`Phòng vẽ: ${room.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ room.name }}
                </h2>
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <!-- Room Settings for Owner -->
                    <button 
                        v-if="room.owner_id == user.id"
                        @click="isSettingsOpen = true"
                        :class="room.is_public ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600'"
                        class="inline-flex items-center px-3 py-1 border rounded-md hover:opacity-80 transition text-sm font-medium"
                        title="Cài đặt phòng"
                    >
                        <span class="me-1">⚙️ {{ room.is_public ? '🌐 Public' : '🔒 Private' }}</span>
                    </button>

                    <button 
                        @click="copyInviteLink"
                        class="inline-flex items-center px-3 py-1 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-100 transition text-sm font-medium"
                    >
                        {{ copyStatus }}
                    </button>
                    <span class="text-sm text-gray-500 hidden lg:inline">Chủ phòng: {{ room.owner.name }}</span>
                    <Link :href="route('rooms.index')" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Quay lại</Link>
                </div>
            </div>
        </template>

        <div class="py-4">
            <div class="max-w-[95%] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4 text-gray-900 dark:text-gray-100">
                        <!-- Toolbar -->
                        <div class="mb-4 flex flex-wrap gap-2 border-b dark:border-gray-700 pb-4">
                            <button 
                                @click="selectedTool = 'pencil'"
                                :class="{'bg-indigo-600 text-white': selectedTool === 'pencil', 'bg-gray-200 dark:bg-gray-700': selectedTool !== 'pencil'}"
                                class="px-4 py-2 rounded-md transition font-medium"
                            >✏️ Bút chì</button>
                            <button 
                                @click="selectedTool = 'rect'"
                                :class="{'bg-indigo-600 text-white': selectedTool === 'rect', 'bg-gray-200 dark:bg-gray-700': selectedTool !== 'rect'}"
                                class="px-4 py-2 rounded-md transition font-medium"
                            >⬜ Hình chữ nhật</button>
                            <button 
                                @click="selectedTool = 'circle'"
                                :class="{'bg-indigo-600 text-white': selectedTool === 'circle', 'bg-gray-200 dark:bg-gray-700': selectedTool !== 'circle'}"
                                class="px-4 py-2 rounded-md transition font-medium"
                            >⭕ Hình tròn</button>
                            <button 
                                @click="selectedTool = 'eraser'"
                                :class="{'bg-indigo-600 text-white': selectedTool === 'eraser', 'bg-gray-200 dark:bg-gray-700': selectedTool !== 'eraser'}"
                                class="px-4 py-2 rounded-md transition font-medium"
                            >🧼 Tẩy</button>
                            
                            <div class="flex-grow"></div>
                            
                            <div class="flex gap-2 items-center">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button 
                                            type="button" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none transition ease-in-out duration-150 shadow-sm"
                                        >
                                            ⚙️ Tùy chọn
                                            <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900 border-b dark:border-gray-700">Thao tác lịch sử</div>
                                        <button 
                                            @click="undo" 
                                            :disabled="!canUndo"
                                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition"
                                        >
                                            ↩️ Hoàn tác (Undo)
                                        </button>
                                        <button 
                                            @click="redo" 
                                            :disabled="redoStack.length === 0"
                                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition"
                                        >
                                        >
                                            ↪️ Làm lại (Redo)
                                        </button>

                                        <div class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900 border-y dark:border-gray-700 mt-1">Cá nhân & Phân quyền</div>
                                        <button 
                                            v-if="room.owner_id === user.id"
                                            @click="manageMembers"
                                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 transition"
                                        >
                                            👥 Quản lý thành viên
                                        </button>
                                        <button 
                                            v-else
                                            @click="leaveRoom"
                                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 focus:outline-none focus:bg-red-50 dark:focus:bg-red-900/20 transition font-medium"
                                        >
                                            🚪 Rời khỏi phòng vẽ
                                        </button>

                                        <div class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900 border-y dark:border-gray-700 mt-1">Dọn dẹp bảng</div>
                                        <button 
                                            @click="clearMyDrawings"
                                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 transition"
                                        >
                                            🧹 Dọn nét của tôi
                                        </button>
                                        <button 
                                            @click="clearAll"
                                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 focus:outline-none focus:bg-red-50 dark:focus:bg-red-900/20 transition font-medium"
                                        >
                                            {{ room.owner_id == user.id ? '🔥 Xóa sạch tất cả' : '📢 Yêu cầu xóa hết' }}
                                        </button>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Canvas Area -->
                        <div class="bg-white dark:bg-gray-900 border shadow-inner rounded-xl overflow-hidden">
                            <v-stage 
                                :config="stageConfig"
                                @mousedown="handleMouseDown"
                                @mousemove="handleMouseMove"
                                @mouseup="handleMouseUp"
                                @touchstart="handleMouseDown"
                                @touchmove="handleMouseMove"
                                @touchend="handleMouseUp"
                            >
                                <v-layer>
                                    <template v-for="obj in objects" :key="obj.id">
                                        <v-line 
                                            v-if="obj.type === 'pencil' || obj.type === 'eraser'" 
                                            :config="{...obj.data, draggable: true}" 
                                            @dragend="(e) => handleDragEnd(e, obj)"
                                        />
                                        <v-rect 
                                            v-else-if="obj.type === 'rect'" 
                                            :config="{...obj.data, draggable: true}" 
                                            @dragend="(e) => handleDragEnd(e, obj)"
                                        />
                                        <v-circle 
                                            v-else-if="obj.type === 'circle'" 
                                            :config="{...obj.data, draggable: true}" 
                                            @dragend="(e) => handleDragEnd(e, obj)"
                                        />
                                    </template>
                                </v-layer>
                            </v-stage>
                        </div>
                        
                        <div class="mt-4 text-xs text-gray-500 flex justify-between">
                            <span>Sử dụng chuột hoặc cảm ứng để vẽ.</span>
                            <span>Trạng thái: {{ isDrawing ? 'Đang vẽ...' : 'Sẵn sàng' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Settings Modal -->
    <Modal :show="isSettingsOpen" @close="isSettingsOpen = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6 flex items-center">
                <span class="mr-2">⚙️</span> Cấu hình phòng vẽ
            </h2>

            <form @submit.prevent="submitSettings" class="space-y-6">
                <div>
                    <InputLabel for="modal_name" value="Tên phòng" />
                    <TextInput 
                        id="modal_name" 
                        type="text" 
                        class="mt-1 block w-full" 
                        v-model="form.name" 
                        required 
                    />
                    <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                </div>

                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input 
                            id="modal_public" 
                            type="checkbox" 
                            v-model="form.is_public"
                            :true-value="false"
                            :false-value="true"
                            class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:bg-gray-900"
                        >
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="modal_public" class="font-medium text-gray-700 dark:text-gray-300">Chế độ riêng tư (Private)</label>
                        <p class="text-gray-500 dark:text-gray-400">Ẩn phòng khỏi trang chủ cộng đồng và chỉ cho phép truy cập qua mã ID.</p>
                    </div>
                </div>

                <div v-show="!form.is_public">
                    <InputLabel for="modal_password" value="Đổi mật khẩu phòng (Để trống để giữ nguyên)" />
                    <TextInput 
                        id="modal_password" 
                        type="password" 
                        class="mt-1 block w-full" 
                        v-model="form.password" 
                        placeholder="Nhập mật khẩu mới..."
                    />
                    <div v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</div>
                </div>

                <div class="pt-6 border-t dark:border-gray-700">
                    <h3 class="text-sm font-bold text-red-600 dark:text-red-400 uppercase tracking-wider mb-3">Vùng nguy hiểm</h3>
                    <div class="flex items-center justify-between p-4 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-100 dark:border-red-900/30">
                        <div>
                            <p class="text-sm font-bold text-red-800 dark:text-red-200">Xóa phòng này</p>
                            <p class="text-xs text-red-600 dark:text-red-400">Hành động này không thể hoàn tác.</p>
                        </div>
                        <DangerButton type="button" @click="deleteRoom">Xóa ngay</DangerButton>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="isSettingsOpen = false">Hủy</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Lưu thay đổi
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Members Modal -->
    <Modal :show="isMembersOpen" @close="isMembersOpen = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6 flex items-center">
                <span class="mr-2">👥</span> Quản lý thành viên
            </h2>
            <div class="space-y-4 max-h-96 overflow-y-auto">
                <div v-if="!room.users || room.users.length === 0" class="text-gray-500 text-center py-4">Chưa có ai tham gia phòng.</div>
                <div v-for="member in room.users" :key="member.id" class="flex justify-between items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg border dark:border-gray-700">
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100 flex items-center">
                            {{ member.name }} 
                            <span v-if="member.id === room.owner_id" class="ml-2 text-[10px] bg-indigo-100 text-indigo-800 font-bold px-2 py-0.5 rounded uppercase">Chủ phòng</span>
                        </div>
                        <div class="text-xs text-gray-500 flex items-center">
                            {{ member.email }}
                        </div>
                    </div>
                    <button 
                        v-if="member.id !== room.owner_id"
                        @click="kickMember(member.id)"
                        class="text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 font-semibold px-3 py-1.5 rounded-lg text-sm transition border border-transparent hover:border-red-200 dark:hover:border-red-800"
                    >
                        Mời ra
                    </button>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button 
                    @click="isMembersOpen = false"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 tracking-widest hover:bg-gray-700 dark:hover:bg-white transition"
                >
                    Đóng
                </button>
            </div>
        </div>
    </Modal>
</template>

<style>
.konvajs-content {
    cursor: crosshair;
    margin: 0 auto;
}
</style>
