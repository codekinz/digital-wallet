<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import Pusher from 'pusher-js';
import { usePage } from '@inertiajs/vue3';

import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];



const page = usePage();

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

const transactionForm = useForm({
    receiver_id: null,
    amount: 0.0,
});

const receiverInput = ref(null);
const form = transactionForm;

const commission = computed(() => {
    if (!form.amount || isNaN(form.amount)) return '0.00';
    return (form.amount * 0.015).toFixed(2);
});

const totalWithCommission = computed(() => {
    if (!form.amount || isNaN(form.amount)) return '0.00';
    return (form.amount * 1.015).toFixed(2);
});

const isFormValid = computed(() => {
    return form.receiver_id > 0 && form.amount > 0;
});

const formatAmount = () => {
    if (form.amount) {
        form.amount = parseFloat(form.amount).toFixed(2);
    }
};

const focusOnFirstError = () => {
    if (transactionForm.errors.receiver_id) {
        receiverInput.value?.$el?.focus();
    } else if (transactionForm.errors.amount) {
        document.getElementById('amount')?.focus();
    }
};

let pusher: { subscribe: (arg0: string) => any; disconnect: () => void },
    channel: {
        bind: (arg0: string, arg1: (data: any) => void) => void;
        unbind_all: () => void;
    };

const transactions = ref([]);
const balance = ref(0);

const formattedBalance = computed(() => {
  return balance.value.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
});

const fetchTransactions = async (page = 1) => {
  try {
    const { data } = await axios.get(`/api/transactions?page=${page}`);
    transactions.value = data.transactions.data;
    balance.value = data.balance;
  } catch (error) {
    console.error('Error fetching transactions:', error);
  }
};

onMounted(() => {
    fetchTransactions();
    const userId = page.props.auth.user.id; // assuming you share auth.user
    pusher = new Pusher('2a08a48a4c38225d1e28', { cluster: 'ap1' });

    pusher.connection.bind('state_change', (states: { previous: string; current: string }) => {
        console.log('Pusher state changed:', states.previous, '→', states.current);
    });
    channel = pusher.subscribe(`user.${userId}`);
    channel.bind('transaction.created', data => {
        emit('transaction-updated', data);
    });
});

onUnmounted(() => {
    if (channel) channel.unbind_all();
    if (pusher) pusher.disconnect();
});

const emit = defineEmits(['transaction-updated']);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="border-b px-4 py-12 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h2
                        class="text-base font-semibold tracking-wide text-muted-foreground uppercase">
                        Total Balance
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Available funds
                    </p>
                </div>
            </div>

            <!-- ✅ Balance injected here -->
            <div class="space-y-6">
                <div class="flex items-end gap-x-1">
                    <h3
                        class="text-4xl font-bold tracking-wide text-foreground">
                        {{ formattedBalance }}
                    </h3>
                    <span
                        class="text-base font-semibold tracking-wide text-muted-foreground uppercase">
                        units
                    </span>
                </div>
            </div>
        </div>

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
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                class="cursor-pointer"
                                data-test="new-transaction-button">
                                Send Money
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                :data="form"
                                reset-on-success
                                @error="focusOnFirstError"
                                :options="{ preserveScroll: true }"
                                class="space-y-6"
                                v-slot="{
                                    errors,
                                    processing,
                                    reset,
                                    clearErrors,
                                }">
                                <DialogHeader class="space-y-3">
                                    <DialogTitle>
                                        Initiate New Transaction
                                    </DialogTitle>
                                    <DialogDescription>
                                        Enter the recipient's user ID and the
                                        amount to transfer. A 1.5% commission
                                        will be applied.
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="grid gap-4">
                                    <div class="grid gap-2">
                                        <Label for="receiver_id">
                                            Recipient User ID
                                        </Label>
                                        <Input
                                            id="receiver_id"
                                            type="number"
                                            name="receiver_id"
                                            v-model.number="form.receiver_id"
                                            placeholder="Enter recipient's user ID"
                                            ref="receiverInput"
                                            @input="
                                                clearErrors('receiver_id')
                                            " />
                                        <InputError
                                            :message="errors.receiver_id" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="amount">Amount</Label>
                                        <Input
                                            id="amount"
                                            type="number"
                                            name="amount"
                                            v-model.number="form.amount"
                                            placeholder="Enter amount"
                                            step="0.01"
                                            @input="
                                                () => {
                                                    clearErrors('amount');
                                                    formatAmount();
                                                }
                                            "
                                            @blur="formatAmount" />
                                        <InputError :message="errors.amount" />
                                        <p
                                            v-if="form.amount"
                                            class="text-sm text-gray-600">
                                            Commission (1.5%):
                                            {{ commission }} | Total:
                                            {{ totalWithCommission }}
                                        </p>
                                    </div>
                                </div>

                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            class="cursor-pointer"
                                            variant="secondary"
                                            @click="
                                                () => {
                                                    clearErrors();
                                                    reset();
                                                }
                                            "
                                            data-test="cancel-transaction-button">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        class="cursor-pointer"
                                        type="submit"
                                        :disabled="processing || !isFormValid"
                                        data-test="confirm-transaction-button">
                                        Send
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle">
                        <table
                            class="relative min-w-full divide-y divide-gray-300">
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="person in people"
                                    :key="person.email">
                                    <td
                                        class="py-4 pr-3 pl-4 whitespace-nowrap sm:pl-6 lg:pl-8">
                                        <h3
                                            class="text-base font-semibold text-gray-900">
                                            {{ person.name }}
                                        </h3>
                                        <p class="text-xs text-gray-600">
                                            {{ person.email }}
                                        </p>
                                    </td>

                                    <td
                                        class="space-y-2 py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-6 lg:pr-8">
                                        <div
                                            class="flex items-center justify-end gap-x-1">
                                            <p class="text-xl font-semibold">
                                                1,200
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground">
                                                units
                                            </p>
                                        </div>

                                        <span
                                            class="inline-flex items-center rounded-md bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-red-600/10 ring-inset">
                                            Outgoing
                                        </span>
                                        <span
                                            class="inline-flex items-center rounded-md bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-green-600/20 ring-inset">
                                            Incoming
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="flex items-center justify-center pt-6 pb-16">
                            <a
                                href="javascript:void(0)"
                                class="text-sm tracking-wide font-semibold text-gray-900">
                                Load More Transactions
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
