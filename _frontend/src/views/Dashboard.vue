<script setup>
import { ref, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue'
import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

dayjs.extend(relativeTime)

const { error: showError, success: showSuccess } = useToast()

const balance = ref('0.00')
const assets = ref([])
const orders = ref([])
const selectedFilter = ref('open')
const selectedSymbol = ref('all')
const selectedSide = ref('all')
const loading = ref(true)
const loadingOrders = ref(false)
const userId = ref(null)

const instance = getCurrentInstance()
const echo = instance?.appContext.config.globalProperties.$echo

const formatTime = (timestamp) => {
    return dayjs(timestamp).fromNow()
}

const statusMap = {
    open: 1,
    filled: 2,
    cancelled: 3,
}

const filterOrders = (filter) => {
    selectedFilter.value = filter
    fetchOrders()
}

const filterBySymbol = () => {
    fetchOrders()
}

const filterBySide = () => {
    fetchOrders()
}

const fetchProfile = async () => {
    try {
        const response = await api.get('/profile')
        const data = response.data

        balance.value = data.balance || data.data?.balance || '0.00'
        assets.value = data.assets || data.data?.assets || []
        userId.value = data.id || data.data?.id || null
    } catch (err) {
        showError('Failed to load profile data')
    }
}

const fetchOrders = async () => {
    loadingOrders.value = true
    try {
        const statusCode = statusMap[selectedFilter.value]
        const params = { status: statusCode }

        // Add symbol filter if not 'all'
        if (selectedSymbol.value !== 'all') {
            params.symbol = selectedSymbol.value
        }

        // Add side filter if not 'all'
        if (selectedSide.value !== 'all') {
            params.side = selectedSide.value
        }

        const response = await api.get('/orders', { params })
        orders.value = response.data.orders
    } catch (err) {
        showError('Failed to load orders')
    } finally {
        loadingOrders.value = false
    }
}

const handleCancelOrder = async (orderId) => {
    try {
        const response = await api.post(`/orders/${orderId}/cancel`)
        showSuccess(response.data.message)

        fetchOrders(selectedFilter.value)
    } catch (err) {
        console.error('Failed to cancel order:', err)
        showError(err.response?.data?.message || 'Failed to cancel order')
    }
}

onMounted(async () => {
    loading.value = true
    await Promise.all([fetchProfile(), fetchOrders()])
    loading.value = false

    // Set up WebSocket listener for OrderMatched event
    if (echo && userId.value) {
        echo.private(`user.${userId.value}`).listen('OrderMatched', (data) => {
            console.log('OrderMatched event received:', data)

            // Update the specific order in the orders list
            if (data.order) {
                const orderIndex = orders.value.findIndex((o) => o.id === data.order.id)
                if (orderIndex === -1) {
                    fetchOrders()
                } else {
                    // Update the existing order
                    orders.value[orderIndex] = { ...orders.value[orderIndex], ...data.order }
                }
            }

            // refetch profile to update assets
            fetchProfile()
        })
    }
})

onBeforeUnmount(() => {
    // Clean up WebSocket listener
    if (echo && userId.value) {
        echo.leave(`user.${userId.value}`)
    }
})
</script>

<template>
    <div>
        <div class="mb-8 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Welcome back! Here's what's happening today.
                </p>
            </div>
            <div class="bg-white shadow rounded-lg px-6 py-4 border-2 border-indigo-200">
                <p class="text-sm font-medium text-gray-500 mb-1">USD Balance</p>
                <p class="text-3xl font-bold text-indigo-600">
                    ${{
                        parseFloat(balance).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        })
                    }}
                </p>
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-12">
            <svg
                class="animate-spin h-8 w-8 text-indigo-600"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
            </svg>
        </div>

        <div v-else>
            <!-- Assets Table -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Your Assets</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Your cryptocurrency balances and locked amounts
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    ID
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Symbol
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Amount
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Locked Amount
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Last Updated
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="asset in assets" :key="asset.id" class="hover:bg-gray-50">
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
                                >
                                    {{ asset.id }}
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900"
                                >
                                    {{ asset.symbol }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ parseFloat(asset.amount).toFixed(8) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ parseFloat(asset.locked_amount).toFixed(8) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatTime(asset.updated_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="assets.length === 0" class="text-center py-12">
                    <p class="text-gray-500">No assets found.</p>
                </div>
            </div>

            <!-- Recent Orders Table -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Orders</h3>
                    <p class="mt-1 text-sm text-gray-500">Your most recent trading activity</p>
                </div>

                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <!-- Status Filters - Left Side -->
                        <div class="flex flex-wrap gap-2">
                            <button
                                @click="filterOrders('open')"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200"
                                :class="{
                                    'bg-indigo-600 text-white shadow-md hover:bg-indigo-700':
                                        selectedFilter === 'open',
                                    'bg-white text-gray-700 border border-gray-300 hover:border-indigo-300 hover:bg-indigo-50':
                                        selectedFilter !== 'open',
                                }"
                            >
                                Open
                            </button>
                            <button
                                @click="filterOrders('filled')"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200"
                                :class="{
                                    'bg-indigo-600 text-white shadow-md hover:bg-indigo-700':
                                        selectedFilter === 'filled',
                                    'bg-white text-gray-700 border border-gray-300 hover:border-indigo-300 hover:bg-indigo-50':
                                        selectedFilter !== 'filled',
                                }"
                            >
                                Filled
                            </button>
                            <button
                                @click="filterOrders('cancelled')"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200"
                                :class="{
                                    'bg-indigo-600 text-white shadow-md hover:bg-indigo-700':
                                        selectedFilter === 'cancelled',
                                    'bg-white text-gray-700 border border-gray-300 hover:border-indigo-300 hover:bg-indigo-50':
                                        selectedFilter !== 'cancelled',
                                }"
                            >
                                Cancelled
                            </button>
                        </div>

                        <!-- Symbol and Side Filters - Right Side -->
                        <div class="flex flex-wrap gap-3">
                            <select
                                id="symbol-filter"
                                v-model="selectedSymbol"
                                @change="filterBySymbol"
                                class="px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:border-gray-400 transition-colors cursor-pointer"
                            >
                                <option value="all">All Symbols</option>
                                <option value="BTC">BTC</option>
                                <option value="ETH">ETH</option>
                            </select>

                            <select
                                id="side-filter"
                                v-model="selectedSide"
                                @change="filterBySide"
                                class="px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:border-gray-400 transition-colors cursor-pointer"
                            >
                                <option value="all">All Sides</option>
                                <option value="buy">Buy</option>
                                <option value="sell">Sell</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Order ID
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Symbol
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Side
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Amount
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Price
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Time
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="order in orders" :key="order.id">
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
                                >
                                    #{{ order.id }}
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900"
                                >
                                    {{ order.symbol }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        :class="{
                                            'bg-green-100 text-green-800': order.side === 'buy',
                                            'bg-red-100 text-red-800': order.side === 'sell',
                                        }"
                                    >
                                        {{ order.side === 'buy' ? 'Buy' : 'Sell' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ order.amount }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${{ order.price }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatTime(order.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <button
                                        v-if="order.status === 1"
                                        @click="handleCancelOrder(order.id)"
                                        class="text-red-600 hover:text-red-900 font-medium"
                                    >
                                        Cancel
                                    </button>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="loadingOrders" class="flex items-center justify-center py-12">
                    <svg
                        class="animate-spin h-6 w-6 text-indigo-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                </div>
                <div v-else-if="orders.length === 0" class="text-center py-12">
                    <p class="text-gray-500">No orders found.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
