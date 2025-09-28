<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

// Define types
interface Transaction {
  id: number;
  sender: { name: string };
  receiver: { name: string };
  amount: number;
  commission_fee: number;
  created_at: string;
}

interface User {
  balance?: number | string | null; // Allow for potential string or null
}

const page = usePage();
const user = page.props.auth.user as User | null;
const balance = ref<number>(user?.balance ? Number(user.balance) : 0);
const transactions = ref<Transaction[]>([]);
const receiverId = ref('');
const amount = ref<number | null>(null);
const error = ref<string>('');


onMounted(() => {
  if (user) fetchTransactions();
});

const fetchTransactions = async () => {
  try {
    const response = await axios.get('/api/transactions', {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });

    balance.value = Number(response.data.balance) || 0; // Ensure balance is a number
    transactions.value = response.data.transactions || [];
    error.value = ''; // Clear error on successful fetch
  } catch (err) {
    console.error('Error fetching transactions:', err);
    error.value = (err as any)?.response?.data?.error || 'Failed to load transactions. Please try again.';
  }
};

const transfer = async () => {
  if (!amount.value || !receiverId.value) {
    error.value = 'Please fill all fields.';
    return;
  }

  try {
    await axios.post('/api/transactions', {
      receiver_id: receiverId.value,
      amount: amount.value
    }, {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });
    await fetchTransactions();
    receiverId.value = '';
    amount.value = null;
    error.value = '';
  } catch (err) {
    console.error('Transfer failed:', err);
    if((err as any)?.response?.data?.error || 'Failed to load transactions. Please try again.') {
     error.value= (err as any)?.response?.data?.error;
    }

    if((err as any)?.response?.data?.message || 'Transfer failed. Please check your input and try again.') {
      error.value = (err as any)?.response?.data?.message;
    }

  }
};

const formatDate = (date: string) => {
  return new Date(date).toLocaleString();
};
</script>

<template>
  <Head title="Wallet" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-gray-100 p-6">
      <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Mini Wallet</h1>
        <div v-if="user" class="space-y-6">
          <!-- Balance Display -->
          <div class="bg-green-100 p-4 rounded-lg text-center">
            <h2 class="text-xl font-semibold text-green-800">Your Balance</h2>
            <p class="text-2xl font-bold text-green-700">{{ balance.toFixed(2) }} USD</p>
          </div>

          <!-- Error Display -->
          <div v-if="error" class="bg-red-100 p-4 rounded-lg text-center">
            <p class="text-sm text-red-800">{{ error }}</p>
          </div>

          <!-- Transfer Form -->
          <div class="bg-blue-50 p-4 rounded-lg">
            <h2 class="text-xl font-semibold text-blue-800 mb-4">Send Money</h2>
            <form @submit.prevent="transfer" class="space-y-4">
              <div>
                <label for="receiverId" class="block text-sm font-medium text-gray-700">Receiver ID</label>
                <input v-model="receiverId" id="receiverId" type="text" placeholder="Enter Receiver ID" required
                  class="mt-1 block w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" />
              </div>
              <div>
                <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                <input v-model.number="amount" id="amount" type="number" step="0.01" placeholder="Enter Amount" required
                  class="mt-1 block w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" />
              </div>
              <button type="submit"
                class="w-full bg-blue-600 text-white p-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Send
              </button>
            </form>
          </div>

          <!-- Transaction History -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Transaction History</h2>
            <ul v-if="transactions" class="space-y-2">
              <li v-for="transaction in transactions" :key="transaction.id"
                class="p-3 bg-white border border-gray-200 rounded-md flex justify-between items-center">
                <span class="text-gray-700">
                  {{ transaction.sender.name }} → {{ transaction.receiver.name }}: {{transaction.amount }} USD
                  <span class="text-red-500">(Fee: {{ transaction.commission_fee}} USD)</span>
                </span>
                <span class="text-sm text-gray-500">{{ formatDate(transaction.created_at) }}</span>
              </li>
            </ul>
            <p v-else class="text-gray-500 text-center">No transactions yet.</p>
          </div>
        </div>
        <div v-else class="text-center space-x-4">
          <a href="/login" class="text-blue-600 hover:underline">Login</a>
          <a href="/register" class="text-blue-600 hover:underline">Register</a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
