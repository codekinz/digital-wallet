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
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import Pusher from 'pusher-js';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();

// Use reactive refs for data that will update in real-time
const transactions = ref<any[]>(page.props.transactions?.data || []);
const balance = ref<number>(parseFloat(page.props.balance) || 0);

const hasMorePages = computed(() => page.props.transactions?.next_page_url !== null);

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
            const dialogClose = document.querySelector('[data-test="cancel-transaction-button"]') as HTMLButtonElement;
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
        router.get(page.props.transactions.next_page_url, {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['transactions', 'balance'],
            onSuccess: () => {
                // Update local state with new data
                transactions.value = page.props.transactions?.data || [];
                balance.value = parseFloat(page.props.balance) || 0;
            }
        });
    }
};

// Pusher setup for real-time updates
let pusher: Pusher | null = null;

const handleTransactionCreated = (data: any) => {
    console.log('🎉 handleTransactionCreated CALLED!', data);
    
    // Parse the data based on the structure from Laravel logs
    const transactionData = data.transaction || data;
    const senderBalance = data.sender_balance;
    const receiverBalance = data.receiver_balance;
    
    console.log('📊 Transaction data:', transactionData);
    
    // Add the new transaction to the top of the list
    const newTransaction = {
        id: transactionData.id,
        sender_id: transactionData.sender_id,
        receiver_id: transactionData.receiver_id,
        amount: parseFloat(transactionData.amount),
        commission_fee: parseFloat(transactionData.commission_fee),
        created_at: transactionData.created_at,
        sender: transactionData.sender,
        receiver: transactionData.receiver
    };
    
    transactions.value.unshift(newTransaction);
    console.log('📈 Added transaction to list');
    
    // Update balance
    const currentUserId = page.props.auth?.user?.id;
    console.log('👤 Current user ID:', currentUserId);
    
    if (currentUserId === transactionData.sender_id) {
        balance.value = parseFloat(senderBalance);
        console.log('💸 Updated SENDER balance to:', senderBalance);
    } else if (currentUserId === transactionData.receiver_id) {
        balance.value = parseFloat(receiverBalance);
        console.log('💸 Updated RECEIVER balance to:', receiverBalance);
    }
    
    console.log('✅ Final balance:', balance.value);
};

onMounted(() => {
    console.log('🚀 Dashboard mounted - User ID:', page.props.auth?.user?.id);

    const user = page.props.auth?.user;
    if (!user) {
        console.error('❌ No user found');
        return;
    }

    console.log('🔌 Setting up Pusher for user:', user.id);

    pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true,
        encrypted: true,
    });

    // Debug connection
    pusher.connection.bind('connected', () => {
        console.log('✅ Pusher connected successfully');
    });

    pusher.connection.bind('error', (error: any) => {
        console.error('❌ Pusher error:', error);
    });

    const channelName = `user.${user.id}`;
    console.log('📡 Subscribing to channel:', channelName);
    
    const channel = pusher.subscribe(channelName);
    
    channel.bind('subscription_succeeded', () => {
        console.log('✅ Subscribed to channel:', channelName);
    });

    channel.bind('subscription_error', (error: any) => {
        console.error('❌ Subscription error:', error);
    });

    // Listen for ALL possible event names
    channel.bind('TransactionCreated', handleTransactionCreated);
    channel.bind('transaction.created', handleTransactionCreated);
    channel.bind('transaction-created', handleTransactionCreated);
    channel.bind('App\\Events\\TransactionCreated', handleTransactionCreated);
    channel.bind('.transaction.created', handleTransactionCreated);
    
    // Global event listener - this should catch EVERYTHING
    channel.bind_global((eventName: string, data: any) => {
        console.log('🌐 GLOBAL EVENT - Name:', eventName, 'Data:', data);
        
        // Manually call our handler for any transaction-related event
        if (eventName.includes('transaction') || eventName.includes('Transaction')) {
            console.log('🎯 Processing transaction event via global handler');
            handleTransactionCreated(data);
        }
    });

    console.log('👂 Listening for events on channel:', channelName);
});

// Test function to verify the handler works
const testEventHandler = () => {
    console.log('🧪 Testing event handler...');
    
    const mockData = {
        transaction: {
            id: 999,
            sender_id: 1,
            receiver_id: 12,
            amount: "25.00",
            commission_fee: "0.38",
            created_at: new Date().toISOString(),
            sender: { id: 1, name: "Test Sender" },
            receiver: { id: 12, name: "Test Receiver" }
        },
        sender_balance: "9715.00",
        receiver_balance: "334.00"
    };
    
    console.log('🧪 Calling handleTransactionCreated directly');
    handleTransactionCreated(mockData);
};

onUnmounted(() => {
    if (pusher) {
        console.log('🔌 Disconnecting Pusher...');
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
        <nav class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-bold text-gray-900">Digital Wallet</h1>
                        <span class="ml-4 text-sm text-gray-500">User #{{ page.props.auth?.user?.id }}</span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button @click="refreshData" class="text-gray-700 hover:text-gray-900">
                            Refresh
                        </button>
                        <span class="text-sm text-gray-700">Balance: {{ formattedBalance }}</span>
                        <button @click="logout" class="text-gray-700 hover:text-gray-900">
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Display flash messages -->
            <div v-if="page.props.flash?.success" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ page.props.flash.success }}
            </div>
            
            <div v-if="page.props.flash?.error" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                {{ page.props.flash.error }}
            </div>

            <!-- Balance Section -->
            <div class="bg-white overflow-hidden shadow rounded-lg mb-8">
                <div class="px-4 py-5 sm:p-6">
                    <h2 class="text-base font-semibold tracking-wide text-gray-500 uppercase">Total Balance</h2>
                    <p class="mt-1 text-xs text-gray-500">Available funds</p>
                    <div class="flex items-end gap-x-1 mt-4">
                        <h3 class="text-4xl font-bold tracking-wide text-gray-900">{{ formattedBalance }}</h3>
                        <span class="text-base font-semibold tracking-wide text-gray-500 uppercase">units</span>
                    </div>
                </div>
            </div>

            <!-- Transactions Section -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-center mb-6">
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
                                    <Button class="cursor-pointer" data-test="new-transaction-button">
                                        Send Money
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <form @submit.prevent="submitTransaction" class="space-y-6">
                                        <DialogHeader class="space-y-3">
                                            <DialogTitle>
                                                Initiate New Transaction
                                            </DialogTitle>
                                            <DialogDescription>
                                                Enter the recipient's user ID and the amount to transfer.
                                                A 1.5% commission will be applied.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <div class="grid gap-4">
                                            <div class="grid gap-2">
                                                <Label for="receiver_id">Recipient User ID</Label>
                                                <Input
                                                    id="receiver_id"
                                                    type="number"
                                                    name="receiver_id"
                                                    v-model.number="transactionForm.receiver_id"
                                                    placeholder="Enter recipient's user ID"
                                                    ref="receiverInput"
                                                    :class="{ 'border-red-500': transactionForm.errors.receiver_id }"
                                                />
                                                <InputError :message="transactionForm.errors.receiver_id" />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label for="amount">Amount</Label>
                                                <Input
                                                    id="amount"
                                                    type="number"
                                                    name="amount"
                                                    v-model.number="transactionForm.amount"
                                                    placeholder="Enter amount"
                                                    step="0.01"
                                                    :class="{ 'border-red-500': transactionForm.errors.amount }"
                                                    @blur="formatAmount"
                                                />
                                                <InputError :message="transactionForm.errors.amount" />
                                                <p v-if="transactionForm.amount" class="text-sm text-gray-600">
                                                    Commission (1.5%): {{ commission }} | Total:
                                                    {{ totalWithCommission }}
                                                </p>
                                            </div>
                                        </div>

                                        <DialogFooter class="gap-2">
                                            <DialogClose as-child>
                                                <Button
                                                    class="cursor-pointer"
                                                    variant="secondary"
                                                    @click="transactionForm.clearErrors()"
                                                    data-test="cancel-transaction-button"
                                                    type="button"
                                                >
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                class="cursor-pointer"
                                                type="submit"
                                                :disabled="transactionForm.processing || !isFormValid"
                                                data-test="confirm-transaction-button"
                                            >
                                                <span v-if="transactionForm.processing">Processing...</span>
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
                        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        <tr v-for="tx in transactions" :key="tx.id">
                                            <td class="py-4 pl-4 pr-3 text-sm sm:pl-6">
                                                <div class="flex items-center">
                                                    <div>
                                                        <h3 class="text-base font-semibold text-gray-900">
                                                            {{ tx.sender_id === page.props.auth?.user?.id ? 'Sent to User #' + tx.receiver_id : 'Received from User #' + tx.sender_id }}
                                                        </h3>
                                                        <p class="text-xs text-gray-600">
                                                            {{ new Date(tx.created_at).toLocaleString() }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                                <div class="flex flex-col items-end">
                                                    <p class="text-xl font-semibold" :class="tx.sender_id === page.props.auth?.user?.id ? 'text-red-600' : 'text-green-600'">
                                                        {{ tx.sender_id === page.props.auth?.user?.id ? '-' : '+' }}{{ Math.abs(tx.amount).toLocaleString() }}
                                                    </p>
                                                    <span
                                                        v-if="tx.sender_id === page.props.auth?.user?.id"
                                                        class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/10"
                                                    >
                                                        Outgoing
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20"
                                                    >
                                                        Incoming
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Show message when no transactions -->
                                <div v-if="transactions.length === 0" class="text-center py-8">
                                    <p class="text-gray-500">No transactions found.</p>
                                </div>

                                <!-- Load More Button -->
                                <div v-if="hasMorePages" class="flex items-center justify-center pt-6 pb-4">
                                    <Button
                                        @click="loadMoreTransactions"
                                        variant="outline"
                                        class="cursor-pointer"
                                    >
                                        Load More Transactions
                                    </Button>
                                </div>

                                <!-- No more pages message -->
                                <div v-if="!hasMorePages && transactions.length > 0" class="text-center py-4">
                                    <p class="text-gray-500">No more transactions to load.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>