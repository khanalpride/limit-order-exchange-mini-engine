<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const { success, error: showError } = useToast()

const form = reactive({
    symbol: '',
    side: '',
    price: '',
    amount: '',
})

const errors = reactive({
    symbol: '',
    side: '',
    price: '',
    amount: '',
})

const loading = ref(false)

const symbols = [
    { value: 'BTC', label: 'Bitcoin (BTC)' },
    { value: 'ETH', label: 'Ethereum (ETH)' },
]

const sides = [
    { value: 'buy', label: 'Buy' },
    { value: 'sell', label: 'Sell' },
]

const clearErrors = () => {
    errors.symbol = ''
    errors.side = ''
    errors.price = ''
    errors.amount = ''
}

const clearForm = () => {
    form.symbol = ''
    form.side = ''
    form.price = ''
    form.amount = ''
}

const handleSubmit = async () => {
    clearErrors()
    loading.value = true

    try {
        const response = await api.post('/orders', {
            symbol: form.symbol,
            side: form.side,
            price: parseFloat(form.price),
            amount: parseFloat(form.amount),
        })

        success('Order placed successfully!')

        clearForm()

        // Navigate to dashboard after successful submission
        setTimeout(() => {
            router.push('/dashboard')
        }, 1500)
    } catch (err) {
        if (err.response?.status === 422) {
            // Laravel validation errors
            const validationErrors = err.response.data.errors

            if (validationErrors) {
                Object.keys(validationErrors).forEach((key) => {
                    if (errors.hasOwnProperty(key)) {
                        errors[key] = validationErrors[key][0]
                    }
                })

                showError('Please fix the validation errors and try again.')
            }
        } else if (err.response?.data?.message) {
            showError(err.response.data.message)
        } else {
            showError('Failed to place order. Please try again.')
        }
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="max-w-2xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Create Limit Order</h1>
            <p class="mt-2 text-sm text-gray-600">
                Place a new limit order to buy or sell cryptocurrency
            </p>
        </div>

        <div class="bg-white shadow rounded-lg">
            <form @submit.prevent="handleSubmit" class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Symbol Select -->
                    <div>
                        <label for="symbol" class="block text-sm font-medium text-gray-700 mb-1">
                            Symbol <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="symbol"
                            v-model="form.symbol"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            :class="{ 'border-red-300': errors.symbol }"
                        >
                            <option value="" disabled>Select a symbol</option>
                            <option
                                v-for="symbol in symbols"
                                :key="symbol.value"
                                :value="symbol.value"
                            >
                                {{ symbol.label }}
                            </option>
                        </select>
                        <p v-if="errors.symbol" class="mt-1 text-sm text-red-600">
                            {{ errors.symbol }}
                        </p>
                    </div>

                    <!-- Side Select -->
                    <div>
                        <label for="side" class="block text-sm font-medium text-gray-700 mb-1">
                            Side <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="side"
                            v-model="form.side"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            :class="{ 'border-red-300': errors.side }"
                        >
                            <option value="" disabled>Select side</option>
                            <option v-for="side in sides" :key="side.value" :value="side.value">
                                {{ side.label }}
                            </option>
                        </select>
                        <p v-if="errors.side" class="mt-1 text-sm text-red-600">
                            {{ errors.side }}
                        </p>
                    </div>

                    <!-- Price Input -->
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">
                            Price (USD) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative rounded-md shadow-sm">
                            <div
                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                            >
                                <span class="text-gray-500 sm:text-sm">$</span>
                            </div>
                            <input
                                id="price"
                                v-model="form.price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="block w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                :class="{ 'border-red-300': errors.price }"
                            />
                        </div>
                        <p v-if="errors.price" class="mt-1 text-sm text-red-600">
                            {{ errors.price }}
                        </p>
                    </div>

                    <!-- Amount Input -->
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">
                            Amount (Quantity) <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.00000001"
                            min="0"
                            placeholder="0.00"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            :class="{ 'border-red-300': errors.amount }"
                        />
                        <p v-if="errors.amount" class="mt-1 text-sm text-red-600">
                            {{ errors.amount }}
                        </p>
                    </div>
                </div>

                <!-- Order Summary -->
                <div
                    v-if="form.price && form.amount"
                    class="bg-gray-50 rounded-lg p-4 border border-gray-200"
                >
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Order Summary</h3>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Symbol:</span>
                            <span class="font-medium text-gray-900">{{ form.symbol || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Side:</span>
                            <span
                                class="font-medium"
                                :class="{
                                    'text-green-600': form.side === 'buy',
                                    'text-red-600': form.side === 'sell',
                                }"
                            >
                                {{ form.side ? form.side.toUpperCase() : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Price:</span>
                            <span class="font-medium text-gray-900"
                                >${{ parseFloat(form.price).toFixed(2) }}</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount:</span>
                            <span class="font-medium text-gray-900">{{
                                parseFloat(form.amount).toFixed(8)
                            }}</span>
                        </div>
                        <div class="border-t border-gray-300 pt-2 mt-2">
                            <div class="flex justify-between">
                                <span class="text-gray-900 font-semibold">Subtotal:</span>
                                <span class="text-gray-900 font-semibold">
                                    ${{
                                        (parseFloat(form.price) * parseFloat(form.amount)).toFixed(
                                            2,
                                        )
                                    }}
                                </span>
                            </div>
                            <div v-if="form.side === 'buy'" class="flex justify-between mt-1">
                                <span class="text-gray-600">Commission (1.5%):</span>
                                <span class="text-gray-600">
                                    ${{
                                        (
                                            parseFloat(form.price) *
                                            parseFloat(form.amount) *
                                            0.015
                                        ).toFixed(2)
                                    }}
                                </span>
                            </div>
                            <div class="flex justify-between mt-2 pt-2 border-t border-gray-300">
                                <span class="text-gray-900 font-bold text-base">Total:</span>
                                <span class="text-indigo-600 font-bold text-base">
                                    ${{
                                        form.side === 'buy'
                                            ? (
                                                  parseFloat(form.price) *
                                                  parseFloat(form.amount) *
                                                  1.015
                                              ).toFixed(2)
                                            : (
                                                  parseFloat(form.price) * parseFloat(form.amount)
                                              ).toFixed(2)
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                    <button
                        type="button"
                        @click="router.push('/dashboard')"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        :disabled="loading"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="!loading">Place Order</span>
                        <span v-else class="flex items-center">
                            <svg
                                class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
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
                            Placing Order...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped></style>
