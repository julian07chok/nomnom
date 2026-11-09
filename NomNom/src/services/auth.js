import { computed, ref } from "vue";
import users from "../data/users.json";
import { apiRequest, setCsrfToken } from "./api";

const STORAGE_KEY = "nomnom-demo-user-id";
const REGISTERED_USERS_KEY = "nomnom-registered-users";
const PROFILE_OVERRIDES_KEY = "nomnom-profile-overrides";

// ── Locally-registered users ────────────────────────────────
function loadRegisteredUsers() {
  try {
    const raw = localStorage.getItem(REGISTERED_USERS_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function saveRegisteredUsers(list) {
  try {
    localStorage.setItem(REGISTERED_USERS_KEY, JSON.stringify(list));
  } catch {
    // Storage unavailable — registration still works this session.
  }
}

// ── Profile overrides ──────────────────────────────────────
function loadProfileOverrides() {
  try {
    const raw = localStorage.getItem(PROFILE_OVERRIDES_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}

function saveProfileOverrides(map) {
  try {
    localStorage.setItem(PROFILE_OVERRIDES_KEY, JSON.stringify(map));
  } catch {
    // Storage unavailable — edits still work this session.
  }
}

/**
 * All users = demo users from users.json + locally-registered users,
 * with profile overrides applied on top.
 */
export function allUsers() {
  const overrides = loadProfileOverrides();
  return [...users, ...loadRegisteredUsers()].map((user) => {
    const patch = overrides[user.id];
    return patch ? { ...user, ...patch } : user;
  });
}

/**
 * Find a single user by username (case-insensitive).
 * Returns the public profile (no password).
 */
export function getUserByUsername(username) {
  if (!username) return null;

  const normalized = String(username).toLowerCase();
  const found = allUsers().find((u) => u.username.toLowerCase() === normalized);

  if (!found) return null;

  const { password, ...profile } = found;
  return {
    ...profile,
    displayName: profile.displayName ?? profile.username,
  };
}

function publicUser(user) {
  if (!user) return null;

  const { password, ...profile } = user;

  return {
    ...profile,
    displayName: profile.displayName ?? profile.username,
  };
}

const currentUser = ref(null);
const isLoggedIn = computed(() => currentUser.value !== null);

let sessionLoaded = false;
let sessionRequest = null;

function applySession(data) {
  currentUser.value = data.user;
  setCsrfToken(data.csrfToken);
  sessionLoaded = true;
}

async function initAuth() {
  if (sessionLoaded) return;

  if (!sessionRequest) {
    sessionRequest = apiRequest("session.php")
      .then(applySession)
      .finally(() => {
        sessionRequest = null;
      });
  }

  return sessionRequest;
}

/**
 * Where should the "author" link point?
 * Own account → My Profile (/profile)
 * Someone else → User Profile (/users/:username)
 */
export function profileLinkFor(username) {
  if (!username) return "/";
  if (
    currentUser.value &&
    currentUser.value.username.toLowerCase() === String(username).toLowerCase()
  ) {
    return "/profile";
  }
  return `/users/${username}`;
}

async function login(identifier, password) {
  await initAuth();

  const data = await apiRequest("login.php", {
    method: "POST",
    body: {
      identifier: identifier.trim(),
      password,
    },
  });

  applySession(data);
  return currentUser.value;
}

async function register({
  username,
  email,
  password,
  confirmPassword,
  acceptedTerms,
}) {
  await initAuth();

  const data = await apiRequest("register.php", {
    method: "POST",
    body: {
      username: username.trim(),
      email: email.trim(),
      password,
      confirmPassword,
      acceptedTerms,
    },
  });

  applySession(data);
  return currentUser.value;
}

async function updateProfile({ displayName, bio, avatar }) {
  if (!currentUser.value) {
    throw new Error("You must be logged in to update your profile.");
  }

  const userId = currentUser.value.id;

  const overrides = loadProfileOverrides();
  overrides[userId] = {
    ...(overrides[userId] || {}),
    displayName: displayName.trim(),
    bio: bio.trim(),
    avatar: avatar.trim(),
  };
  saveProfileOverrides(overrides);

  currentUser.value = {
    ...currentUser.value,
    displayName: displayName.trim(),
    bio: bio.trim(),
    avatar: avatar.trim(),
  };

  return currentUser.value;
}

async function logout() {
  await initAuth();

  const data = await apiRequest("logout.php", {
    method: "POST",
  });

  applySession(data);
}

export function useAuth() {
  return {
    currentUser: computed(() => currentUser.value),
    isLoggedIn,
    initAuth,
    login,
    register,
    updateProfile,
    logout,
    profileLinkFor,
  };
}
