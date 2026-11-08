import { computed, ref } from 'vue'
import users from '../data/users.json'

const STORAGE_KEY = 'nomnom-demo-user-id'
const REGISTERED_USERS_KEY = 'nomnom-registered-users'
const PROFILE_OVERRIDES_KEY = 'nomnom-profile-overrides'

// ── Locally-registered users (stored in localStorage) ──────
function loadRegisteredUsers() {
    try {
        const raw = localStorage.getItem(REGISTERED_USERS_KEY)
        if (!raw) return []
        const parsed = JSON.parse(raw)
        return Array.isArray(parsed) ? parsed : []
    } catch {
        return []
    }
}

function saveRegisteredUsers(list) {
    try {
        localStorage.setItem(REGISTERED_USERS_KEY, JSON.stringify(list))
    } catch {
        // Storage unavailable — registration still works this session.
    }
}

// ── Profile overrides ─────────────────────────────────────
// Edits to demo users (which live in users.json) are stored here
// so we can't accidentally corrupt the seed data.
// Shape: { [userId]: { displayName?, bio?, avatar? } }
function loadProfileOverrides() {
    try {
        const raw = localStorage.getItem(PROFILE_OVERRIDES_KEY)
        if (!raw) return {}
        const parsed = JSON.parse(raw)
        return parsed && typeof parsed === 'object' ? parsed : {}
    } catch {
        return {}
    }
}

function saveProfileOverrides(map) {
    try {
        localStorage.setItem(PROFILE_OVERRIDES_KEY, JSON.stringify(map))
    } catch {
        // Storage unavailable — edits still work this session.
    }
}

// Demo users + locally-registered users, with overrides applied
function allUsers() {
    const overrides = loadProfileOverrides()
    return [...users, ...loadRegisteredUsers()].map((user) => {
        const patch = overrides[user.id]
        return patch ? { ...user, ...patch } : user
    })
}

function restoreUser() {
    try {
        const id = Number(localStorage.getItem(STORAGE_KEY))
        return allUsers().find((user) => user.id === id) ?? null
    } catch {
        return null
    }
}

function publicUser(user) {
    if (!user) return null

    const { password, ...profile } = user

    // Ensure displayName always exists for the UI
    return {
        ...profile,
        displayName: profile.displayName ?? profile.username,
    }
}

const currentUser = ref(publicUser(restoreUser()))
const isLoggedIn = computed(() => currentUser.value !== null)

async function login(email, password) {
    const normalizedEmail = email.trim().toLowerCase()

    const user = allUsers().find(
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

/**
 * Register a new account.
 * TODO: replace body with POST /api/auth/register.php
 */
async function register({ username, email, password }) {
    const normalizedUsername = username.trim().toLowerCase()
    const normalizedEmail = email.trim().toLowerCase()
    const existing = allUsers()

    if (existing.some((u) => u.email.toLowerCase() === normalizedEmail)) {
        throw new Error('An account with this email already exists.')
    }

    if (
        existing.some((u) => u.username.toLowerCase() === normalizedUsername)
    ) {
        throw new Error('This username is already taken.')
    }

    const nextId = existing.reduce((max, u) => Math.max(max, u.id), 0) + 1

    const newUser = {
        id: nextId,
        username: normalizedUsername,
        displayName: username.trim(),
        email: normalizedEmail,
        password, // mock only — hash in PHP
        avatar: `https://i.pravatar.cc/150?u=${encodeURIComponent(normalizedEmail)}`,
        bio: '',
        postIds: [],
        savedPostIds: [],
        likedPostIds: [],
        createdAt: new Date().toISOString(),
    }

    const registered = loadRegisteredUsers()
    registered.push(newUser)
    saveRegisteredUsers(registered)

    currentUser.value = publicUser(newUser)

    try {
        localStorage.setItem(STORAGE_KEY, String(newUser.id))
    } catch {
        // Session still active in memory.
    }

    return publicUser(newUser)
}

/**
 * Update the current user's profile (displayName, bio, avatar).
 * TODO: replace body with PUT /api/users/me
 */
async function updateProfile({ displayName, bio, avatar }) {
    if (!currentUser.value) {
        throw new Error('You must be logged in to update your profile.')
    }

    const userId = currentUser.value.id

    const overrides = loadProfileOverrides()
    overrides[userId] = {
        ...(overrides[userId] || {}),
        displayName: displayName.trim(),
        bio: bio.trim(),
        avatar: avatar.trim(),
    }
    saveProfileOverrides(overrides)

    // Update the reactive currentUser so every page sees the change
    currentUser.value = {
        ...currentUser.value,
        displayName: displayName.trim(),
        bio: bio.trim(),
        avatar: avatar.trim(),
    }

    return currentUser.value
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
        register,
        updateProfile,
        logout,
    }
}