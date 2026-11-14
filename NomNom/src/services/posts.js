import { computed, ref } from "vue";
import { apiRequest } from "./api";
import { useAuth } from "./auth";

// Shared post collection and loading feedback for views
const postList = ref([]);
const isLoading = ref(false);
const postError = ref("");
const postsLoaded = ref(false);

let pendingRequest = null;

// Resolve uploaded image paths for the current app location
function resolveImageUrl(url) {
  if (typeof url !== "string" || url === "") return "";

  if (url.startsWith("api/") || url.startsWith("nomnom-api/")) {
    const cleaned = url.startsWith("api/")
      ? url.replace(/^api\//, "nomnom-api/")
      : url;

    const base = import.meta.env.BASE_URL.endsWith("/")
      ? import.meta.env.BASE_URL
      : import.meta.env.BASE_URL + "/";

    return `${base}${cleaned}`;
  }

  return url;
}

// Load posts with optional nearby filtering and closest-first backend sorting
function loadPosts(options = {}) {
  // Reuse an existing request instead of loading twice
  if (pendingRequest) return pendingRequest;

  const params = new URLSearchParams();

  if (options.latitude != null && options.longitude != null) {
    params.set("lat", options.latitude);
    params.set("lng", options.longitude);
    if (options.radiusKm != null) {
      params.set("radius", options.radiusKm);
    }
  }

  const queryString = params.toString();
  const endpoint = queryString ? `posts.php?${queryString}` : "posts.php";

  pendingRequest = (async () => {
    isLoading.value = true;
    postError.value = "";

    try {
      const data = await apiRequest(endpoint);

      if (!Array.isArray(data.posts)) {
        throw new Error("The server returned an invalid post list.");
      }

      // Prepare image paths before storing the returned posts
      postList.value = data.posts.map((post) => ({
        ...post,
        authorAvatar: resolveImageUrl(post.authorAvatar),
        coverImage: resolveImageUrl(post.coverImage),
        gallery: post.gallery.map(resolveImageUrl),
        galleryItems: post.galleryItems.map((image) => ({
          id: image.id,
          url: resolveImageUrl(image.url),
        })),
      }));
      postsLoaded.value = true;
      return true;
    } catch (error) {
      postList.value = [];
      postsLoaded.value = false;
      postError.value = error.message || "Unable to load posts.";
      return false;
    } finally {
      isLoading.value = false;
    }
  })().finally(() => {
    pendingRequest = null;
  });

  return pendingRequest;
}

// Publish post details and photo files using multipart form data
async function createPost(data) {
  const { initAuth, isLoggedIn } = useAuth();

  await initAuth();

  if (!isLoggedIn.value) {
    throw new Error("Please log in before publishing.");
  }

  const body = new FormData();

  const fields = [
    "title",
    "description",
    "restaurantName",
    "address",
    "location",
    "cuisine",
    "priceRange",
  ];

  for (const field of fields) {
    body.append(field, data[field]);
  }

  body.append("latitude", data.latitude);
  body.append("longitude", data.longitude);

  for (const file of data.files) {
    body.append("photos[]", file);
  }

  const result = await apiRequest("posts.php", {
    method: "POST",
    body,
  });

  return { id: Number(result.postId) };
}

// Send edited post details and the photo plan to PHP
async function updatePost(id, data) {
  const { initAuth, isLoggedIn } = useAuth();
  await initAuth();

  if (!isLoggedIn.value) {
    throw new Error("Please log in before editing.");
  }

  const result = await apiRequest(
    `manage-post.php?id=${encodeURIComponent(id)}`,
    {
      method: "PUT",
      body: {
        title: data.title,
        description: data.description,
        restaurantName: data.restaurantName,
        address: data.address,
        location: data.location,
        cuisine: data.cuisine,
        priceRange: data.priceRange,
        latitude: data.latitude,
        longitude: data.longitude,
        photos: data.photos,
      },
    },
  );

  return { id: Number(result.postId) };
}

// Request deletion of an owned post
async function deletePost(id) {
  const { initAuth, isLoggedIn } = useAuth();
  await initAuth();

  if (!isLoggedIn.value) {
    throw new Error("Please log in before deleting.");
  }

  await apiRequest(`manage-post.php?id=${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
}

// Add or remove a like or bookmark and use the confirmed backend result
async function changePostInteraction(id, type, active) {
  const { initAuth, currentUser, isLoggedIn } = useAuth();

  await initAuth();

  if (!isLoggedIn.value) {
    throw new Error("Please log in to continue.");
  }

  const viewerId = currentUser.value.id;

  const result = await apiRequest(
    `post-interactions.php?id=${encodeURIComponent(id)}&type=${type}`,
    {
      method: active ? "PUT" : "DELETE",
    },
  );

  // Do not apply another account's result if the login changed during the request
  if (currentUser.value?.id === viewerId) {
    postList.value = postList.value.map((post) =>
      String(post.id) === String(result.post.id)
        ? { ...post, ...result.post }
        : post,
    );
  }

  return result.post;
}

// Set whether the current account likes this post
function setLike(id, active) {
  return changePostInteraction(id, "like", active);
}

// Set whether the current account has bookmarked this post
function setSaved(id, active) {
  return changePostInteraction(id, "save", active);
}

// Expose reactive post data and actions to views
export function usePosts() {
  return {
    posts: computed(() => postList.value),
    isLoading: computed(() => isLoading.value),
    postError: computed(() => postError.value),
    postsLoaded: computed(() => postsLoaded.value),
    loadPosts,
    createPost,
    updatePost,
    deletePost,
    setLike,
    setSaved,
  };
}
