<!-- src/views/PostDetailView.vue -->
<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { usePosts } from "../services/posts";
import { useAuth } from "../services/auth";
import commentsData from "../data/comments.json";

import PostGallery from "../components/post/PostGallery.vue";
import PostActions from "../components/post/PostActions.vue";
import CommentList from "../components/comment/CommentList.vue";
import CommentForm from "../components/comment/CommentForm.vue";

import "../assets/css/post-detail.css";

const route = useRoute();
const router = useRouter();

const { posts } = usePosts();
const { currentUser, isLoggedIn } = useAuth();

// ────────────────────────────────────────────────────────────
// Persistence helpers (mock only — swap for PHP later)
// ────────────────────────────────────────────────────────────
// The real backend will replace the three keys below with:
//   - POST /api/posts/:id/like
//   - POST /api/posts/:id/save
//   - POST /api/posts/:id/comments
//   - DELETE /api/posts/:id
//   - DELETE /api/comments/:id
// For now we persist per-user state in localStorage so the UI
// survives a refresh and the profile Saved tab can read it.

const SAVED_KEY = "nomnom-saved-posts";       // { userId: [postId, ...] }
const LIKED_KEY = "nomnom-liked-posts";       // { userId: [postId, ...] }
const DELETED_POSTS_KEY = "nomnom-deleted-posts"; // [postId, ...]
const EXTRA_COMMENTS_KEY = "nomnom-extra-comments"; // [comment, ...]

function readJSON(key, fallback) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return fallback;
    const parsed = JSON.parse(raw);
    return parsed ?? fallback;
  } catch {
    return fallback;
  }
}

function writeJSON(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // Storage unavailable — UI still works this session.
  }
}

// Load "saved by which user" map
const savedByUser = ref(readJSON(SAVED_KEY, {}));
const likedByUser = ref(readJSON(LIKED_KEY, {}));
const deletedPostIds = ref(readJSON(DELETED_POSTS_KEY, []));
const extraComments = ref(readJSON(EXTRA_COMMENTS_KEY, []));

// ─── 1. Post ────────────────────────────────────────────────
const post = ref(null);
const notFound = ref(false);

function loadPost(id) {
  const numericId = Number(id);

  if (deletedPostIds.value.includes(numericId)) {
    notFound.value = true;
    post.value = null;
    return;
  }

  const found = posts.value.find((p) => String(p.id) === String(id));

  if (!found) {
    notFound.value = true;
    post.value = null;
    return;
  }

  notFound.value = false;
  post.value = { ...found };
}

watch(() => route.params.id, (newId) => loadPost(newId), { immediate: true });

// Re-load if the underlying posts array changes (e.g. after delete)
watch(posts, () => loadPost(route.params.id));

// ─── 2. Owner check ────────────────────────────────────────
const isOwner = computed(
  () =>
    isLoggedIn.value &&
    post.value?.authorName === currentUser.value?.username,
);

// ─── 3. Like / Save (persisted per user) ───────────────────
const liked = computed(() => {
  if (!currentUser.value || !post.value) return false;
  const list = likedByUser.value[currentUser.value.id] || [];
  return list.map(String).includes(String(post.value.id));
});

const saved = computed(() => {
  if (!currentUser.value || !post.value) return false;
  const list = savedByUser.value[currentUser.value.id] || [];
  return list.map(String).includes(String(post.value.id));
});

// Derived display count: base likes from JSON + (liked ? +1 : 0)
const displayLikes = computed(() => {
  if (!post.value) return 0;
  return post.value.likes + (liked.value ? 1 : 0);
});

function toggleListInMap(mapRef, key, userId, postId) {
  const map = { ...mapRef.value };
  const current = (map[userId] || []).map(String);
  const target = String(postId);
  const idx = current.indexOf(target);

  if (idx >= 0) current.splice(idx, 1);
  else current.push(target);

  map[userId] = current;
  mapRef.value = map;
  writeJSON(key, map);
}

function toggleLike() {
  if (!isLoggedIn.value) {
    router.push({
      name: "login",
      query: { redirect: route.fullPath },
    });
    return;
  }
  // TODO: replace with POST /api/posts/:id/like (or DELETE for unlike)
  toggleListInMap(likedByUser, LIKED_KEY, currentUser.value.id, post.value.id);
}

function toggleSave() {
  if (!isLoggedIn.value) {
    router.push({
      name: "login",
      query: { redirect: route.fullPath },
    });
    return;
  }
  // TODO: replace with POST /api/posts/:id/save (or DELETE for unsave)
  toggleListInMap(savedByUser, SAVED_KEY, currentUser.value.id, post.value.id);
}

// ─── 4. Owner actions ──────────────────────────────────────
function goEdit() {
  router.push({ name: "edit-post", params: { id: post.value.id } });
}

function deletePost() {
  if (!confirm("Delete this post? This cannot be undone.")) return;

  // TODO: replace with DELETE /api/posts/:id
  const updated = [...deletedPostIds.value, post.value.id];
  deletedPostIds.value = updated;
  writeJSON(DELETED_POSTS_KEY, updated);

  router.push({ name: "home" });
}

// ─── 5. Comments ───────────────────────────────────────────
// Seed comments from JSON + any added this browser session
function allCommentsFor(postId) {
  const seeded = commentsData.filter((c) => c.postId === Number(postId));
  const added = extraComments.value.filter(
    (c) => Number(c.postId) === Number(postId),
  );
  return [...added, ...seeded];
}

const comments = ref([]);

watch(
  post,
  (p) => {
    comments.value = p ? allCommentsFor(p.id) : [];
  },
  { immediate: true },
);

function addComment(text) {
  if (!isLoggedIn.value) return;

  const newComment = {
    id: `local-${Date.now()}`,
    postId: post.value.id,
    userId: currentUser.value.id,
    userName: currentUser.value.username,
    userAvatar: currentUser.value.avatar,
    text,
    images: [],
    createdAt: new Date().toISOString(),
  };

  // TODO: replace with POST /api/posts/:id/comments
  const updated = [newComment, ...extraComments.value];
  extraComments.value = updated;
  writeJSON(EXTRA_COMMENTS_KEY, updated);

  comments.value = [newComment, ...comments.value];
}

function deleteComment(commentId) {
  // TODO: replace with DELETE /api/comments/:id
  const updated = extraComments.value.filter(
    (c) => String(c.id) !== String(commentId),
  );
  extraComments.value = updated;
  writeJSON(EXTRA_COMMENTS_KEY, updated);

  comments.value = comments.value.filter(
    (c) => String(c.id) !== String(commentId),
  );
}
</script>

<template>
  <main class="post-detail">
    <div class="container post-detail-inner">
      <!-- 404 -->
      <div v-if="notFound" class="not-found">
        <h1>Post not found</h1>
        <p>The post you're looking for doesn't exist or was removed.</p>
        <RouterLink class="not-found-link" to="/">Back to Discover</RouterLink>
      </div>

      <!-- Post -->
      <article v-else-if="post" class="post-article">
        <header class="post-header">
          <RouterLink :to="`/users/${post.authorName}`" class="post-author">
            <img
              :src="post.authorAvatar"
              :alt="post.authorName"
              class="post-author-avatar"
            />
            <div>
              <p class="post-author-name">{{ post.authorName }}</p>
              <p class="post-date">
                {{ new Date(post.createdAt).toLocaleDateString() }}
              </p>
            </div>
          </RouterLink>

          <span class="post-cuisine">{{ post.cuisine }}</span>
        </header>

        <h1 class="post-title">{{ post.title }}</h1>

        <PostGallery :images="post.gallery" :alt="post.title" />

        <PostActions
          :likes="displayLikes"
          :liked="liked"
          :saved="saved"
          :is-owner="isOwner"
          :is-logged-in="isLoggedIn"
          @toggle-like="toggleLike"
          @toggle-save="toggleSave"
          @edit="goEdit"
          @delete="deletePost"
        />

        <section class="post-info">
          <h2 class="post-restaurant">
            <i class="bi bi-shop" aria-hidden="true"></i>
            {{ post.restaurantName }}
          </h2>
          <ul class="post-meta">
            <li>
              <i class="bi bi-geo-alt" aria-hidden="true"></i>
              {{ post.address }}
            </li>
            <li>
              <i class="bi bi-currency-dollar" aria-hidden="true"></i>
              {{ post.priceRange }}
            </li>
            <li>
              <i class="bi bi-tag" aria-hidden="true"></i>
              {{ post.location }}
            </li>
          </ul>
        </section>

        <section class="post-description">
          <h2>About this place</h2>
          <p>{{ post.description }}</p>
        </section>

        <!-- Comments -->
        <CommentList
          :comments="comments"
          :current-user="currentUser?.username || ''"
          @delete-comment="deleteComment"
        />

        <CommentForm :is-logged-in="isLoggedIn" @submit-comment="addComment" />
      </article>
    </div>
  </main>
</template>