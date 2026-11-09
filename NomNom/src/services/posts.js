import { computed, ref } from "vue";
import { apiRequest } from "./api";

const postList = ref([]);
const isLoading = ref(false);
const postError = ref("");
const postsLoaded = ref(false);

let pendingRequest = null;

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

      postList.value = data.posts;
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

// These remain exported so existing forms can still import them.
// Their database implementations come in the next stages.
function createPost() {
  throw new Error("Post creation is temporarily unavailable.");
}

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
