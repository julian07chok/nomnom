//router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuth } from '../services/auth'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/',
            name: 'home',
            component: HomeView
        },
        {
            path: '/search',
            name: 'search',
            component: () => import('../views/SearchView.vue')
        },
        {
            path: '/posts/:id',
            name: 'post-detail',
            component: () => import('../views/PostDetailView.vue')
        },
        {
            path: '/create',
            name: 'create-post',
            component: () => import('../views/CreatePostView.vue'), meta: { requiresAuth: true }
        },
        {
            path: '/posts/:id/edit', name: 'edit-post', component: () => import('../views/EditPostView.vue'), meta: { requiresAuth: true }
        },
        {
            path: '/login',
            name: 'login', component: () => import('../views/LoginView.vue')
        },
        {
            path: '/register',
            name: 'register',
            component: () => import('../views/RegisterView.vue')
        },
        {
            path: '/profile',
            name: 'my-profile',
            component: () => import('../views/MyProfileView.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/profile/edit',
            name: 'edit-profile',
            component: () => import('../views/EditProfileView.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/saved',
            name: 'saved',
            component: () => import('../views/SavedPostsView.vue'),
            meta: { requiresAuth: true }
        },
        {
            path: '/users/:username',
            name: 'user-profile',
            component: () => import('../views/UserProfileView.vue')
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('../views/NotFoundView.vue')
        },
    ],
})

router.beforeEach((to) => {
    const { isLoggedIn } = useAuth()

    if (to.meta.requiresAuth && !isLoggedIn.value) {
        return {
            name: 'login',
            query: { redirect: to.fullPath },
        }
    }

    if (to.name === 'login' && isLoggedIn.value) {
        return { name: 'home' }
    }
})

export default router