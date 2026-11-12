<script setup>
import AppButton from "../common/AppButton.vue";

defineProps({
  likes: { type: Number, required: true },
  liked: { type: Boolean, default: false },
  saved: { type: Boolean, default: false },
  isOwner: { type: Boolean, default: false },
  isLoggedIn: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  deleting: { type: Boolean, default: false },
});

const emit = defineEmits(["toggle-like", "toggle-save", "edit", "delete"]);
</script>

<template>
  <div class="post-actions">
    <AppButton
      variant="secondary"
      :class="{ 'is-liked': liked }"
      :disabled="busy || deleting"
      :aria-pressed="liked"
      :aria-label="`${liked ? 'Unlike' : 'Like'} this post; ${likes} likes`"
      @click="emit('toggle-like')"
    >
      <i
        :class="liked ? 'bi bi-heart-fill' : 'bi bi-heart'"
        aria-hidden="true"
      ></i>
      {{ likes }}
    </AppButton>

    <AppButton
      variant="secondary"
      :class="{ 'is-saved': saved }"
      :disabled="busy || deleting"
      :aria-pressed="saved"
      @click="emit('toggle-save')"
    >
      <i
        :class="saved ? 'bi bi-bookmark-fill' : 'bi bi-bookmark'"
        aria-hidden="true"
      ></i>
      {{ saved ? "Saved" : "Save" }}
    </AppButton>

    <AppButton
      v-if="isOwner"
      variant="secondary"
      :disabled="busy || deleting"
      @click="emit('edit')"
    >
      <i class="bi bi-pencil-square" aria-hidden="true"></i>
      Edit
    </AppButton>

    <AppButton
      v-if="isOwner"
      variant="danger"
      :disabled="busy"
      :loading="deleting"
      loading-text="Deleting…"
      @click="emit('delete')"
    >
      <i class="bi bi-trash" aria-hidden="true"></i>
      Delete
    </AppButton>
  </div>
</template>

<style scoped src="../../assets/css/post-actions.css"></style>
