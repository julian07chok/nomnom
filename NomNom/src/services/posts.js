import { computed, ref } from "vue";
import seedPosts from "../data/posts.json";

const STORAGE_KEY = "nomnom-created-posts";

function restorePosts() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
    return Array.isArray(saved) ? saved : [];
  } catch {
    return [];
  }
}

const createdPosts = ref(restorePosts());

const posts = computed(() => [
  ...createdPosts.value,
  ...seedPosts,
]);

function createPost(data, user) {
  if (!user) {
    throw new Error("Please log in before publishing.");
  }

  const post = {
    ...data,
    id: crypto.randomUUID(),
    authorId: user.id,
    authorName: user.username,
    authorAvatar: user.avatar,
    likes: 0,
    likedByMe: false,
    savedByMe: false,
    createdAt: new Date().toISOString(),
    commentIds: [],
  };

  const updatedPosts = [post, ...createdPosts.value];

  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(updatedPosts));
  } catch {
    throw new Error(
      "Unable to save this post. Browser storage may be full or unavailable. Try fewer or smaller photos.",
    );
  }

  createdPosts.value = updatedPosts;
  return post;
}

export function usePosts() {
  return { posts, createPost };
}