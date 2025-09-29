<script setup lang="ts">
import { dashboard, wallet, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Welcome" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-gray-50 to-white dark:from-[#0a0a0a] dark:to-[#161615] p-6 text-[#1b1b18] dark:text-[#EDEDEC]"
    >
        <!-- Header -->
        <header class="absolute top-6 right-6 flex gap-4">
            <template v-if="$page.props.auth?.user">

            </template>
            <template v-else>
                <Link
                    :href="login()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-sm hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800 transition"
                >
                    Log in
                </Link>
                <Link
                    :href="register()"
                    class="px-4 py-2 rounded-md bg-[#1b1b18] text-white text-sm hover:bg-black dark:bg-white dark:text-black dark:hover:bg-gray-200 transition"
                >
                    Register
                </Link>
            </template>
        </header>

        <!-- Hero Section -->
        <main class="max-w-2xl text-center space-y-6">
            <h1 class="text-4xl font-bold tracking-tight">
                Welcome to <span class="text-[#1b1b18] dark:text-[#EDEDEC]">My Digital Wallet</span>
            </h1>
            <p class="text-gray-600 dark:text-gray-400 text-lg">
                A simple, secure, and smart way to manage your money.
            </p>

            <div class="flex justify-center">
                <a
                    href="/dashboard"
                    class="px-6 py-3 rounded-lg bg-[#1b1b18] text-white text-lg font-medium shadow hover:scale-105 hover:shadow-lg transition dark:bg-white dark:text-black"
                >
                    Open Wallet
                </a>
            </div>
        </main>
    </div>
</template>
