<script setup></script>

<template>
    <div class="min-h-screen bg-gray-100 flex flex-col">
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <h1 class="text-2xl font-bold text-gray-900">Limit Order Exchange</h1>
            </div>
        </header>

        <main class="grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="w-full">
                <router-view />
            </div>
        </main>

        <footer class="bg-white shadow mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <p class="text-center text-gray-500 text-sm">
                    &copy; 2025 Limit Order Exchange. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped></style>
