import { computed, ref } from "vue";
import { apiRequest, setCsrfToken } from "./api";

// Shared account state used across pages
const currentUser = ref(null);
const isLoggedIn = computed(() => currentUser.value !== null);

let sessionLoaded = false;
let sessionRequest = null;

// Prepare profile details and resolve uploaded avatar paths
function normalizeProfile(user) {
  if (!user) return null;

  const avatar = typeof user.avatar === "string" ? user.avatar : "";

  let resolvedAvatar = avatar;

  if (avatar.startsWith("api/") || avatar.startsWith("nomnom-api/")) {
    const cleaned = avatar.startsWith("api/")
      ? avatar.replace(/^api\//, "nomnom-api/")
      : avatar;

    const base = import.meta.env.BASE_URL.endsWith("/")
      ? import.meta.env.BASE_URL
      : import.meta.env.BASE_URL + "/";

    resolvedAvatar = `${base}${cleaned}`;
  }

  return {
    ...user,
    displayName: user.displayName ?? user.username,
    avatar: resolvedAvatar,
  };
}

// Update the current account and security token from PHP
function applySession(data) {
  currentUser.value = normalizeProfile(data.user);
  setCsrfToken(data.csrfToken);
  sessionLoaded = true;
}

// Restore the session once and share any request already running
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

// Load a public profile and return null when it does not exist
export async function getUserByUsername(username) {
  if (!username) return null;

  try {
    const data = await apiRequest(
      `user.php?username=${encodeURIComponent(username)}`,
    );

    return normalizeProfile(data.user);
  } catch (error) {
    if (error.status === 404) return null;
    throw error;
  }
}

// Use My Profile for the current account and a public link for others
export function profileLinkFor(username) {
  if (!username) return "/";

  if (
    currentUser.value &&
    currentUser.value.username.toLowerCase() === String(username).toLowerCase()
  ) {
    return "/profile";
  }

  return `/users/${encodeURIComponent(username)}`;
}

// Sign in using a username or email and apply the returned session
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

// Create an account and apply the returned session
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

// Save profile changes for the logged-in account
async function updateProfile({ displayName, bio, avatar }) {
  await initAuth();

  if (!currentUser.value) {
    throw new Error("Please log in to update your profile.");
  }

  const body = {
    displayName: displayName.trim(),
    bio: bio.trim(),
  };

  // Keep the stored avatar path when the avatar has not changed
  const requestedAvatar = avatar.trim();

  if (requestedAvatar !== currentUser.value.avatar) {
    body.avatar = requestedAvatar;
  }

  const data = await apiRequest("profile.php", {
    method: "PUT",
    body,
  });

  applySession(data);
  return currentUser.value;
}

// End the login session and update shared account state
async function logout() {
  await initAuth();

  const data = await apiRequest("logout.php", {
    method: "POST",
  });

  applySession(data);
}

// Expose reactive account data and authentication actions to views
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
