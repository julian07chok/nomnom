import { computed, ref } from "vue";
import { apiRequest, setCsrfToken } from "./api";

const currentUser = ref(null);
const isLoggedIn = computed(() => currentUser.value !== null);

let sessionLoaded = false;
let sessionRequest = null;

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

function applySession(data) {
  currentUser.value = normalizeProfile(data.user);
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
  await initAuth();

  if (!currentUser.value) {
    throw new Error("Please log in to update your profile.");
  }

  const body = {
    displayName: displayName.trim(),
    bio: bio.trim(),
  };

  // Omit an unchanged avatar so PHP retains its original stored path.
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
