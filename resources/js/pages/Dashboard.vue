<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const people = [
    {
        name: 'Lindsay Walton',
        title: 'Front-end Developer',
        email: 'lindsay.walton@example.com',
        role: 'Member',
    },
    {
        name: 'Courtney Henry',
        title: 'Designer',
        email: 'courtney.henry@example.com',
        role: 'Admin',
    },
    {
        name: 'Tom Cook',
        title: 'Director of Product',
        email: 'tom.cook@example.com',
        role: 'Member',
    },
    {
        name: 'Whitney Francis',
        title: 'Copywriter',
        email: 'whitney.francis@example.com',
        role: 'Admin',
    },
    {
        name: 'Leonard Krasner',
        title: 'Senior Designer',
        email: 'leonard.krasner@example.com',
        role: 'Owner',
    },
    {
        name: 'Floyd Miles',
        title: 'Principal Designer',
        email: 'floyd.miles@example.com',
        role: 'Member',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mt-8 px-4 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-lg font-semibold text-gray-900">
                        Recent Transactions
                    </h1>
                    <p class="mt-2 text-sm text-gray-700">
                        Your latest financial activity
                    </p>
                </div>
                <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                    <Button>Initiate a Transaction</Button>
                </div>
            </div>
            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle">
                        <table
                            class="relative min-w-full divide-y divide-gray-300"
                        >
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="person in people"
                                    :key="person.email"
                                >
                                    <td
                                        class="py-4 pr-3 pl-4 whitespace-nowrap sm:pl-6 lg:pl-8"
                                    >
                                        <h3
                                            class="text-base font-medium text-gray-900"
                                        >
                                            {{ person.name }}
                                        </h3>
                                        <p class="text-xs text-gray-600">
                                            {{ person.title }}
                                        </p>
                                    </td>

                                    <td
                                        class="py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-6 lg:pr-8"
                                    >
                                        <p class="text-lg font-bold">-$1,200</p>

                                        <span
                                            class="inline-flex items-center rounded-md bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-red-600/10 ring-inset"
                                        >
                                            Outgoing
                                        </span>
                                        <span
                                            class="inline-flex items-center rounded-md bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-green-600/20 ring-inset"
                                        >
                                            Incoming
                                            </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
