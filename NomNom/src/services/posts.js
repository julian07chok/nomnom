import { computed, ref } from "vue";
import { apiRequest } from "./api";
import { useAuth } from "./auth";

const postList = ref([]);
const isLoading = ref(false);
const postError = ref("");
const postsLoaded = ref(false);

let pendingRequest = null;

function resolveImageUrl(url) {
  if (typeof url !== "string") return "";

  if (url.startsWith("api/")) {
    return `${import.meta.env.BASE_URL}${url}`;
  }

  return url;
}

function loadPosts() {
  // Share an existing request if another component is loading posts.
  if (pendingRequest) return pendingRequest;

  pendingRequest = (async () => {
    isLoading.value = true;
    postError.value = "";

    try {
      const data = await apiRequest("posts.php");

      if (!Array.isArray(data.posts)) {
        throw new Error("The server returned an invalid post list.");
      }

      postList.value = data.posts.map((post) => ({
        ...post,
        coverImage: resolveImageUrl(post.coverImage),
        gallery: post.gallery.map(resolveImageUrl),
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

  for (const file of data.files) {
    body.append("photos[]", file);
  }

  const result = await apiRequest("posts.php", {
    method: "POST",
    body,
  });

  return { id: Number(result.postId) };
}

// These remain exported so existing forms can still import them.
// Their database implementations come in the next stages.

function updatePost() {
  throw new Error("Post editing is temporarily unavailable.");
}

function deletePost() {
  throw new Error("Post deletion is temporarily unavailable.");
}

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
  };
}
