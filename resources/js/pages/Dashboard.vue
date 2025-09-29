<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Pusher from 'pusher-js';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();

// Use reactive refs for data that will update in real-time
const transactions = ref<any[]>(page.props.transactions?.data || []);
const balance = ref<number>(parseFloat(page.props.balance) || 0);

const hasMorePages = computed(
    () => page.props.transactions?.next_page_url !== null,
);

const formattedBalance = computed(() => {
    return balance.value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
});

// Use Inertia's useForm for form handling
const transactionForm = useForm({
    receiver_id: null,
    amount: 0.0,
});

const receiverInput = ref(null);

const commission = computed(() => {
    if (!transactionForm.amount || isNaN(transactionForm.amount)) return '0.00';
    return (transactionForm.amount * 0.015).toFixed(2);
});

const totalWithCommission = computed(() => {
    if (!transactionForm.amount || isNaN(transactionForm.amount)) return '0.00';
    return (transactionForm.amount * 1.015).toFixed(2);
});

const isFormValid = computed(() => {
    return transactionForm.receiver_id > 0 && transactionForm.amount > 0;
});

const formatAmount = () => {
    if (transactionForm.amount) {
        transactionForm.amount = parseFloat(transactionForm.amount).toFixed(2);
    }
};

const focusOnFirstError = () => {
    if (transactionForm.errors.receiver_id) {
        receiverInput.value?.$el?.focus();
    } else if (transactionForm.errors.amount) {
        document.getElementById('amount')?.focus();
    }
};

// Handle form submission with Inertia
const submitTransaction = () => {
    transactionForm.post('/transactions', {
        preserveScroll: true,
        onSuccess: () => {
            // Reset form
            transactionForm.reset();

            // Close dialog
            const dialogClose = document.querySelector(
                '[data-test="cancel-transaction-button"]',
            ) as HTMLButtonElement;
            if (dialogClose) dialogClose.click();

            // Show success message from flash
            if (page.props.flash?.success) {
                alert(page.props.flash.success);
            }
        },
        onError: () => {
            alert('Transaction failed. Please check the input.');
            focusOnFirstError();
        },
    });
};

// Load more transactions using Inertia
const loadMoreTransactions = () => {
    if (page.props.transactions?.next_page_url) {
        router.get(
            page.props.transactions.next_page_url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['transactions', 'balance'],
                onSuccess: () => {
                    // Update local state with new data
                    transactions.value = page.props.transactions?.data || [];
                    balance.value = parseFloat(page.props.balance) || 0;
                },
            },
        );
    }
};

// Pusher setup for real-time updates
let pusher: Pusher | null = null;

// Add the missing refreshData function
const refreshData = () => {
    router.reload({
        preserveScroll: true,
        only: ['transactions', 'balance'],
        onSuccess: () => {
            transactions.value = page.props.transactions?.data || [];
            balance.value = parseFloat(page.props.balance) || 0;
            console.log('Data refreshed manually');
        },
    });
};

const handleTransactionCreated = data => {
    if (!data.transaction || !data.sender_balance || !data.receiver_balance) {
        return;
    }

    const transactionData = data.transaction;
    const senderBalance = data.sender_balance;
    const receiverBalance = data.receiver_balance;

    const newTransaction = {
        id: transactionData.id,
        sender_id: transactionData.sender_id,
        receiver_id: transactionData.receiver_id,
        amount: parseFloat(transactionData.amount),
        commission_fee: parseFloat(transactionData.commission_fee || 0),
        created_at: transactionData.created_at,
        sender: transactionData.sender || {
            id: transactionData.sender_id,
            name: 'Unknown',
        },
        receiver: transactionData.receiver || {
            id: transactionData.receiver_id,
            name: 'Unknown',
        },
    };

    transactions.value.unshift(newTransaction);

    const currentUserId = page.props.auth?.user?.id;
    if (currentUserId === transactionData.sender_id) {
        balance.value = parseFloat(senderBalance);
    } else if (currentUserId === transactionData.receiver_id) {
        balance.value = parseFloat(receiverBalance);
    }

    if (currentUserId === transactionData.receiver_id) {
        alert(
            `You received ${transactionData.amount} from ${transactionData.sender?.name || 'User #' + transactionData.sender_id}`,
        );
    }
};

onMounted(() => {
    const user = page.props.auth?.user;
    if (!user) return;

    const pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: import.meta.env.VITE_PUSHER_APP_USE_TLS === 'true',
    });

    const channelName = `public-user.${user.id}`;
    const channel = pusher.subscribe(channelName);

    channel.bind('pusher:subscription_error', error => {
        console.error('Subscription error:', error);
    });

    channel.bind('TransactionCreated', data => {
        handleTransactionCreated(data);
    });
});

onUnmounted(() => {
    if (pusher) {
        console.log('Disconnecting Pusher...');
        pusher.disconnect();
        pusher = null;
    }
});

// Logout function
const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <Head title="Dashboard" />

    <!-- Simple Layout -->
    <div class="min-h-screen bg-gray-50">
        <!-- Navigation -->
        <nav class="border-b bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex items-center">
                        <h1 class="text-xl font-bold text-gray-900">
                            Digital Wallet
                        </h1>
                        <span class="ml-4 text-sm text-gray-500">
                            User #{{ page.props.auth?.user?.id }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button
                            @click="refreshData"
                            class="text-gray-700 hover:text-gray-900">
                            Refresh
                        </button>
                        <span class="text-sm text-gray-700">
                            Balance: {{ formattedBalance }}
                        </span>
                        <button
                            @click="logout"
                            class="text-gray-700 hover:text-gray-900">
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
            <!-- Display flash messages -->
            <div
                v-if="page.props.flash?.success"
                class="mb-4 rounded border border-green-400 bg-green-100 p-4 text-green-700">
                {{ page.props.flash.success }}
            </div>

            <div
                v-if="page.props.flash?.error"
                class="mb-4 rounded border border-red-400 bg-red-100 p-4 text-red-700">
                {{ page.props.flash.error }}
            </div>

            <!-- Balance Section -->
            <div class="mb-8 overflow-hidden rounded-lg bg-white shadow">
                <div class="px-4 py-5 sm:p-6">
                    <h2
                        class="text-base font-semibold tracking-wide text-gray-500 uppercase">
                        Total Balance
                    </h2>
                    <p class="mt-1 text-xs text-gray-500">Available funds</p>
                    <div class="mt-4 flex items-end gap-x-1">
                        <h3
                            class="text-4xl font-bold tracking-wide text-gray-900">
                            {{ formattedBalance }}
                        </h3>
                        <span
                            class="text-base font-semibold tracking-wide text-gray-500 uppercase">
                            units
                        </span>
                    </div>
                </div>
            </div>

            <!-- Transactions Section -->
            <div class="rounded-lg bg-white shadow">
                <div class="px-4 py-5 sm:p-6">
                    <div class="mb-6 sm:flex sm:items-center">
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
                                    <form
                                        @submit.prevent="submitTransaction"
                                        class="space-y-6">
                                        <DialogHeader class="space-y-3">
                                            <DialogTitle>
                                                Initiate New Transaction
                                            </DialogTitle>
                                            <DialogDescription>
                                                Enter the recipient's user ID
                                                and the amount to transfer. A
                                                1.5% commission will be applied.
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
                                                    v-model.number="
                                                        transactionForm.receiver_id
                                                    "
                                                    placeholder="Enter recipient's user ID"
                                                    ref="receiverInput"
                                                    :class="{
                                                        'border-red-500':
                                                            transactionForm
                                                                .errors
                                                                .receiver_id,
                                                    }" />
                                                <InputError
                                                    :message="
                                                        transactionForm.errors
                                                            .receiver_id
                                                    " />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label for="amount">
                                                    Amount
                                                </Label>
                                                <Input
                                                    id="amount"
                                                    type="number"
                                                    name="amount"
                                                    v-model.number="
                                                        transactionForm.amount
                                                    "
                                                    placeholder="Enter amount"
                                                    step="0.01"
                                                    :class="{
                                                        'border-red-500':
                                                            transactionForm
                                                                .errors.amount,
                                                    }"
                                                    @blur="formatAmount" />
                                                <InputError
                                                    :message="
                                                        transactionForm.errors
                                                            .amount
                                                    " />
                                                <p
                                                    v-if="
                                                        transactionForm.amount
                                                    "
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
                                                        transactionForm.clearErrors()
                                                    "
                                                    data-test="cancel-transaction-button"
                                                    type="button">
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                class="cursor-pointer"
                                                type="submit"
                                                :disabled="
                                                    transactionForm.processing ||
                                                    !isFormValid
                                                "
                                                data-test="confirm-transaction-button">
                                                <span
                                                    v-if="
                                                        transactionForm.processing
                                                    ">
                                                    Processing...
                                                </span>
                                                <span v-else>Send</span>
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>

                    <!-- Transactions List -->
                    <div class="mt-8 flow-root">
                        <div class="-mx-4 -my-2 overflow-x-auto">
                            <div
                                class="inline-block min-w-full py-2 align-middle">
                                <table
                                    class="min-w-full divide-y divide-gray-300">
                                    <tbody
                                        class="divide-y divide-gray-200 bg-white">
                                        <tr
                                            v-for="tx in transactions"
                                            :key="tx.id">
                                            <td
                                                class="py-4 pr-3 pl-4 text-sm sm:pl-6">
                                                <div class="flex items-center">
                                                    <div>
                                                        <h3
                                                            class="text-base font-semibold text-gray-900">
                                                            {{
                                                                tx.sender_id ===
                                                                page.props.auth
                                                                    ?.user?.id
                                                                    ? tx
                                                                          .receiver
                                                                          .name
                                                                    : tx.sender
                                                                          .name
                                                            }}
                                                        </h3>
                                                        <p
                                                            class="text-xs text-gray-600">
                                                            {{
                                                                new Date(
                                                                    tx.created_at,
                                                                ).toLocaleString()
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                class="py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-6">
                                                <div
                                                    class="flex flex-col items-end gap-y-2">
                                                    <div class="flex items-center gap-x-1.5">
                                                        <p
                                                            class="text-2xl font-semibold"
                                                            :class="
                                                                tx.sender_id ===
                                                                page.props.auth
                                                                    ?.user?.id
                                                                    ? 'text-red-500'
                                                                    : 'text-green-500'
                                                            ">
                                                            {{
                                                                tx.sender_id ===
                                                                page.props.auth
                                                                    ?.user?.id
                                                                    ? '-'
                                                                    : '+'
                                                            }}{{
                                                                Math.abs(
                                                                    tx.amount,
                                                                ).toLocaleString()
                                                            }}
                                                        </p>
                                                        <span
                                                            class="mt-1 text-xs font-semibold tracking-wide text-gray-500 uppercase">
                                                            units
                                                        </span>
                                                    </div>

                                                    <span
                                                        v-if="
                                                            tx.sender_id ===
                                                            page.props.auth
                                                                ?.user?.id
                                                        "
                                                        class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/10">
                                                        Outgoing
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20">
                                                        Incoming
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Show message when no transactions -->
                                <div
                                    v-if="transactions.length === 0"
                                    class="py-8 text-center">
                                    <p class="text-gray-500">
                                        No transactions found.
                                    </p>
                                </div>

                                <!-- Load More Button -->
                                <div
                                    v-if="hasMorePages"
                                    class="flex items-center justify-center pt-6 pb-4">
                                    <Button
                                        @click="loadMoreTransactions"
                                        variant="outline"
                                        class="cursor-pointer">
                                        Load More Transactions
                                    </Button>
                                </div>

                                <!-- No more pages message -->
                                <div
                                    v-if="
                                        !hasMorePages && transactions.length > 0
                                    "
                                    class="py-4 text-center">
                                    <p class="text-gray-500">
                                        No more transactions to load.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
