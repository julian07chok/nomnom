import { computed, ref } from 'vue'
import users from '../data/users.json'

const STORAGE_KEY = 'nomnom-demo-user-id'
const REGISTERED_USERS_KEY = 'nomnom-registered-users'
const PROFILE_OVERRIDES_KEY = 'nomnom-profile-overrides'

// ── Locally-registered users ────────────────────────────────
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

// ── Profile overrides ──────────────────────────────────────
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

/**
 * All users = demo users from users.json + locally-registered users,
 * with profile overrides applied on top.
 */
export function allUsers() {
    const overrides = loadProfileOverrides()
    return [...users, ...loadRegisteredUsers()].map((user) => {
        const patch = overrides[user.id]
        return patch ? { ...user, ...patch } : user
    })
}

/**
 * Find a single user by username (case-insensitive).
 * Returns the public profile (no password).
 */
export function getUserByUsername(username) {
    if (!username) return null

    const normalized = String(username).toLowerCase()
    const found = allUsers().find(
        (u) => u.username.toLowerCase() === normalized,
    )

    if (!found) return null

    const { password, ...profile } = found
    return {
        ...profile,
        displayName: profile.displayName ?? profile.username,
    }
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

    return {
        ...profile,
        displayName: profile.displayName ?? profile.username,
    }
}

const currentUser = ref(publicUser(restoreUser()))
const isLoggedIn = computed(() => currentUser.value !== null)

/**
 * Where should the "author" link point?
 * Own account → My Profile (/profile)
 * Someone else → User Profile (/users/:username)
 */
export function profileLinkFor(username) {
    if (!username) return '/'
    if (
        currentUser.value &&
        currentUser.value.username.toLowerCase() ===
            String(username).toLowerCase()
    ) {
        return '/profile'
    }
    return `/users/${username}`
}

async function login(identifier, password) {
    const normalized = identifier.trim().toLowerCase()

    const user = allUsers().find(
        (user) =>
            (user.email.toLowerCase() === normalized ||
                user.username.toLowerCase() === normalized) &&
            user.password === password,
    )

    if (!user) {
        throw new Error('Incorrect email/username or password.')
    }

    currentUser.value = publicUser(user)

    try {
        localStorage.setItem(STORAGE_KEY, String(user.id))
    } catch {
        // Login still works if browser storage is unavailable.
    }
}

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
        password,
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
        profileLinkFor,
    }
}