import { createRouter, createWebHistory } from 'vue-router'
import GuestLayout from '@/layouts/GuestLayout.vue'
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'
import Login from '@/views/Login.vue'
import Dashboard from '@/views/Dashboard.vue'
import CreateOrder from '@/views/CreateOrder.vue'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/',
            redirect: '/login',
        },
        {
            path: '/login',
            component: GuestLayout,
            children: [
                {
                    path: '',
                    name: 'login',
                    component: Login,
                    meta: { requiresGuest: true },
                },
            ],
        },
        {
            path: '/',
            component: AuthenticatedLayout,
            meta: { requiresAuth: true },
            children: [
                {
                    path: 'dashboard',
                    name: 'dashboard',
                    component: Dashboard,
                },
                {
                    path: 'orders/create',
                    name: 'create-order',
                    component: CreateOrder,
                },
            ],
        },
    ],
})

// Navigation guard to protect authenticated routes
router.beforeEach((to, _from, next) => {
    const isAuthenticated = localStorage.getItem('auth_token')

    if (to.meta.requiresAuth && !isAuthenticated) {
        next('/login')
    } else if (to.meta.requiresGuest && isAuthenticated) {
        next('/dashboard')
    } else {
        next()
    }
})

export default router
