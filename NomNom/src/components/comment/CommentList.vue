<script setup>
import { ref } from "vue";
import { useAuth } from "../../services/auth";
import UserAvatar from "../user/UserAvatar.vue";
import "../../assets/css/comment-list.css";

const props = defineProps({
  comments: { type: Array, default: () => [] },
  currentUserId: { type: Number, default: null },
  saveComment: { type: Function, required: true },
  removeComment: { type: Function, required: true },
});

const { profileLinkFor } = useAuth();

const editingId = ref(null);
const editText = ref("");
const busyId = ref(null);
const error = ref("");

function isOwn(comment) {
  return (
    props.currentUserId !== null &&
    Number(comment.userId) === Number(props.currentUserId)
  );
}

function beginEdit(comment) {
  if (busyId.value !== null) return;

  editingId.value = comment.id;
  editText.value = comment.text;
  error.value = "";
}

async function saveEdit(comment) {
  if (busyId.value !== null) return;

  busyId.value = comment.id;
  error.value = "";

  try {
    await props.saveComment(comment.id, editText.value.trim());
    editingId.value = null;
  } catch (err) {
    error.value = err.message || "Unable to edit this comment.";
  } finally {
    busyId.value = null;
  }
}

async function remove(comment) {
  if (busyId.value !== null) return;
  if (!window.confirm("Delete this comment?")) return;

  busyId.value = comment.id;
  error.value = "";

  try {
    await props.removeComment(comment.id);

    if (editingId.value === comment.id) {
      editingId.value = null;
    }
  } catch (err) {
    error.value = err.message || "Unable to delete this comment.";
  } finally {
    busyId.value = null;
  }
}
</script>

<template>
  <section class="comment-list" aria-labelledby="comments-heading">
    <h2 id="comments-heading" class="comment-heading">
      Comments
      <span class="comment-count">{{ comments.length }}</span>
    </h2>

    <p v-if="error" class="alert alert-danger" role="alert">
      {{ error }}
    </p>

    <p v-if="!comments.length" class="comment-empty">
      No comments yet. Be the first to share your thoughts.
    </p>

    <ul v-else class="comment-items">
      <li v-for="comment in comments" :key="comment.id" class="comment-item">
        <UserAvatar :src="comment.userAvatar" :size="36" />

        <div class="comment-body">
          <header class="comment-header">
            <RouterLink
              :to="profileLinkFor(comment.userName)"
              class="comment-author"
            >
              {{ comment.displayName || comment.userName }}
            </RouterLink>

            <span class="comment-time">
              {{ new Date(comment.createdAt).toLocaleDateString() }}
            </span>
          </header>

          <form
            v-if="editingId === comment.id"
            class="my-2"
            @submit.prevent="saveEdit(comment)"
          >
            <label :for="`edit-comment-${comment.id}`" class="form-label">
              Edit your comment
            </label>

            <textarea
              :id="`edit-comment-${comment.id}`"
              v-model="editText"
              class="form-control"
              maxlength="500"
              rows="3"
              :disabled="busyId !== null"
            ></textarea>

            <p v-if="comment.images.length" class="form-text">
              Attached photos will be kept.
            </p>

            <div class="d-flex gap-2 mt-2">
              <button
                class="btn btn-sm btn-dark"
                type="submit"
                :disabled="busyId !== null"
              >
                {{ busyId === comment.id ? "Saving…" : "Save" }}
              </button>

              <button
                class="btn btn-sm btn-outline-dark"
                type="button"
                :disabled="busyId !== null"
                @click="editingId = null"
              >
                Cancel
              </button>
            </div>
          </form>

          <p v-else class="comment-text">{{ comment.text }}</p>

          <ul v-if="comment.images.length" class="comment-images">
            <li v-for="(image, index) in comment.images" :key="image">
              <img
                :src="image"
                :alt="`Comment photo ${index + 1}`"
                loading="lazy"
              />
            </li>
          </ul>

          <div v-if="isOwn(comment)" class="d-flex gap-2 mt-2">
            <button
              class="btn btn-sm btn-outline-dark"
              type="button"
              :disabled="busyId !== null"
              @click="beginEdit(comment)"
            >
              Edit
            </button>

            <button
              class="btn btn-sm btn-outline-danger"
              type="button"
              :disabled="busyId !== null"
              @click="remove(comment)"
            >
              Delete
            </button>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>
