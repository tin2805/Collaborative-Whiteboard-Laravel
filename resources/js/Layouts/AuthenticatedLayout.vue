<script setup>
import { ref, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';

const isSidebarOpen = ref(true);

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

// Auto-hide sidebar on small screens
onMounted(() => {
    if (window.innerWidth < 1024) {
        isSidebarOpen.value = false;
    }
});
</script>

<template>
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900 overflow-hidden text-gray-900 dark:text-gray-100">
        <!-- Sidebar -->
        <aside 
            :class="[
                isSidebarOpen ? 'w-64' : 'w-20',
                'bg-white dark:bg-gray-800 border-r dark:border-gray-700 transition-all duration-300 ease-in-out flex flex-col z-30 fixed lg:static h-full shadow-lg lg:shadow-none',
                !isSidebarOpen && 'hidden lg:flex'
            ]"
        >
            <!-- Sidebar Header / Logo -->
            <div class="h-16 flex items-center px-6 border-b dark:border-gray-700 shrink-0">
                <Link :href="route('dashboard')" class="flex items-center space-x-3 overflow-hidden">
                    <ApplicationLogo class="h-8 w-8 fill-current text-indigo-600 shrink-0" />
                    <span v-if="isSidebarOpen" class="font-bold text-xl dark:text-white truncate">BoardSync</span>
                </Link>
            </div>

            <!-- Menu Items -->
            <nav class="flex-grow py-6 px-4 space-y-2 overflow-y-auto">
                <Link 
                    :href="route('dashboard')"
                    :class="[route().current('dashboard') && !$page.url.includes('tab=') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700']"
                    class="flex items-center p-3 rounded-xl transition group relative"
                >
                    <span class="text-xl">🏠</span>
                    <span v-if="isSidebarOpen" class="ms-3 font-medium">Dashboard</span>
                    <span v-if="!isSidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap pointer-events-none">Dashboard</span>
                </Link>

                <div v-if="isSidebarOpen" class="pt-6 pb-2 px-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Phòng vẽ</div>

                <Link 
                    :href="route('rooms.my')"
                    :class="[route().current('rooms.my') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700']"
                    class="flex items-center p-3 rounded-xl transition group relative"
                >
                    <span class="text-xl">🎨</span>
                    <span v-if="isSidebarOpen" class="ms-3 font-medium text-sm">Phòng của tôi</span>
                    <span v-if="!isSidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap pointer-events-none">Phòng của tôi</span>
                </Link>

                <Link 
                    :href="route('rooms.public')"
                    :class="[route().current('rooms.public') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700']"
                    class="flex items-center p-3 rounded-xl transition group relative"
                >
                    <span class="text-xl">🌐</span>
                    <span v-if="isSidebarOpen" class="ms-3 font-medium text-sm">Khám phá public</span>
                    <span v-if="!isSidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap pointer-events-none">Khám phá</span>
                </Link>

                <div v-if="isSidebarOpen" class="pt-6 pb-2 px-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Cài đặt</div>

                <Link 
                    :href="route('profile.edit')"
                    :class="[route().current('profile.edit') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700']"
                    class="flex items-center p-3 rounded-xl transition group relative"
                >
                    <span class="text-xl">👤</span>
                    <span v-if="isSidebarOpen" class="ms-3 font-medium text-sm">Tài khoản</span>
                    <span v-if="!isSidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap pointer-events-none">Tài khoản</span>
                </Link>

                <Link 
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="w-full flex items-center p-3 rounded-xl transition text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 group relative"
                >
                    <span class="text-xl">🚪</span>
                    <span v-if="isSidebarOpen" class="ms-3 font-medium text-sm text-left">Đăng xuất</span>
                    <span v-if="!isSidebarOpen" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap pointer-events-none">Thoát</span>
                </Link>
            </nav>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t dark:border-gray-700 shrink-0 bg-gray-50/50 dark:bg-gray-900/20">
                <div class="flex items-center space-x-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold shrink-0 shadow-lg shadow-indigo-500/30">
                        {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div v-if="isSidebarOpen" class="truncate overflow-hidden">
                        <p class="text-sm font-bold truncate">{{ $page.props.auth.user.name }}</p>
                        <p class="text-[10px] text-gray-500 truncate">{{ $page.props.auth.user.email }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Overlay for mobile -->
        <div 
            v-if="isSidebarOpen" 
            @click="isSidebarOpen = false"
            class="fixed inset-0 bg-gray-900/50 z-20 lg:hidden backdrop-blur-sm"
        ></div>

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
            <!-- Top Header -->
            <header class="h-16 flex items-center justify-between px-6 bg-white/80 dark:bg-gray-800/80 backdrop-blur-md border-b dark:border-gray-700 sticky top-0 z-20 shrink-0">
                <div class="flex items-center">
                    <button @click="toggleSidebar" class="p-2 -ms-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div class="ms-4 font-semibold text-gray-800 dark:text-gray-200">
                        <slot name="header" />
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <!-- Notifications/Quick action can go here -->
                </div>
            </header>

            <!-- Scrollable content -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 custom-scrollbar">
                <slot />
            </div>
        </main>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #334155;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
