import { computed, ref } from 'vue'
import users from '../data/users.json'

const STORAGE_KEY = 'nomnom-demo-user-id'

function restoreUser() {
    try {
        const id = Number(localStorage.getItem(STORAGE_KEY))
        return users.find((user) => user.id === id) ?? null
    } catch {
        return null
    }
}

function publicUser(user) {
    if (!user) return null

    const { password, ...profile } = user
    return profile
}

const currentUser = ref(publicUser(restoreUser()))
const isLoggedIn = computed(() => currentUser.value !== null)

async function login(email, password) {
    const normalizedEmail = email.trim().toLowerCase()

    const user = users.find(
        (user) =>
            user.email.toLowerCase() === normalizedEmail &&
            user.password === password,
    )

    if (!user) {
        throw new Error('Incorrect email or password.')
    }

    currentUser.value = publicUser(user)

    try {
        localStorage.setItem(STORAGE_KEY, String(user.id))
    } catch {
        // Login still works if browser storage is unavailable.
    }
}

function logout() {
    currentUser.value = null

    try {
        localStorage.removeItem(STORAGE_KEY)
    } catch {
        // The in-memory session has already been cleared.
    }
}

export function useAuth() {
    return {
        currentUser: computed(() => currentUser.value),
        isLoggedIn,
        login,
        logout,
    }
}