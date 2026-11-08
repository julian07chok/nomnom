import { computed, ref } from "vue";
import seedPosts from "../data/posts.json";

const STORAGE_KEY = "nomnom-created-posts";
const DELETED_POSTS_KEY = "nomnom-deleted-posts";

// ── Storage helpers ────────────────────────────────────────
function loadArray(key) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function saveArray(key, list) {
  try {
    localStorage.setItem(key, JSON.stringify(list));
  } catch {
    throw new Error(
      "Unable to save this post. Browser storage may be full or unavailable. Try fewer or smaller photos.",
    );
  }
}

// ── Reactive state ─────────────────────────────────────────
const createdPosts = ref(loadArray(STORAGE_KEY));
const deletedPostIds = ref(loadArray(DELETED_POSTS_KEY));

// ── Combined feed (created + seed, minus deleted) ──────────
const posts = computed(() =>
  [...createdPosts.value, ...seedPosts].filter(
    (post) => !deletedPostIds.value.includes(post.id),
  ),
);

// ── Unique id (works in all browsers) ──────────────────────
// crypto.randomUUID() is undefined in older Safari / non-HTTPS.
function uid() {
  if (typeof crypto !== "undefined" && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return `p-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * Create a new post.
 * TODO: replace with POST /api/posts
 */
function createPost(data, user) {
  if (!user) {
    throw new Error("Please log in before publishing.");
  }

  const post = {
    ...data,
    id: uid(),
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
  saveArray(STORAGE_KEY, updatedPosts);
  createdPosts.value = updatedPosts;
  return post;
}

/**
 * Update an existing post. Only the author can update.
 * TODO: replace with PUT /api/posts/:id
 */
function updatePost(id, data, user) {
  if (!user) {
    throw new Error("Please log in before editing.");
  }

  const stringId = String(id);
  const existing = [...createdPosts.value, ...seedPosts].find(
    (post) => String(post.id) === stringId,
  );

  if (!existing) {
    throw new Error("This post no longer exists.");
  }

  if (String(existing.authorId) !== String(user.id)) {
    throw new Error("You can only edit your own posts.");
  }

  // If it's a seed post, we "fork" it into createdPosts so the
  // edit is saved. Otherwise update in place.
  const isSeed = !createdPosts.value.some(
    (post) => String(post.id) === stringId,
  );

  const updatedPost = {
    ...existing,
    ...data,
    id: existing.id, // never let the id change
    authorId: existing.authorId,
    authorName: existing.authorName,
    authorAvatar: existing.authorAvatar,
    createdAt: existing.createdAt,
    updatedAt: new Date().toISOString(),
  };

  let updated;
  if (isSeed) {
    // Fork: prepend the edited copy so it shadows the seed post
    updated = [updatedPost, ...createdPosts.value];
  } else {
    // Update in place
    updated = createdPosts.value.map((post) =>
      String(post.id) === stringId ? updatedPost : post,
    );
  }

  saveArray(STORAGE_KEY, updated);
  createdPosts.value = updated;
  return updatedPost;
}

/**
 * Delete a post. Only the author can delete.
 * TODO: replace with DELETE /api/posts/:id
 */
function deletePost(id, user) {
  if (!user) {
    throw new Error("Please log in before deleting.");
  }

  const stringId = String(id);
  const existing = [...createdPosts.value, ...seedPosts].find(
    (post) => String(post.id) === stringId,
  );

  if (!existing) {
    throw new Error("This post no longer exists.");
  }

  if (String(existing.authorId) !== String(user.id)) {
    throw new Error("You can only delete your own posts.");
  }

  // If it's a seed post, add its id to deletedPostIds.
  // If it's a created post, remove it from createdPosts.
  const isSeed = !createdPosts.value.some(
    (post) => String(post.id) === stringId,
  );

  if (isSeed) {
    const deleted = [...deletedPostIds.value, existing.id];
    saveArray(DELETED_POSTS_KEY, deleted);
    deletedPostIds.value = deleted;
  } else {
    const filtered = createdPosts.value.filter(
      (post) => String(post.id) !== stringId,
    );
    saveArray(STORAGE_KEY, filtered);
    createdPosts.value = filtered;
  }

  return existing;
}

export function usePosts() {
  return { posts, createPost, updatePost, deletePost };
}