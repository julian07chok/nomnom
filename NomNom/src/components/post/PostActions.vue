<!-- src/components/post/PostActions.vue -->
<script setup>
import '../../assets/css/post-actions.css'

const props = defineProps({
  likes: { type: Number, required: true },
  liked: { type: Boolean, default: false },
  saved: { type: Boolean, default: false },
  isOwner: { type: Boolean, default: false },
  isLoggedIn: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle-like', 'toggle-save', 'edit', 'delete'])
</script>

<template>
  <div class="post-actions">
    <button
      type="button"
      class="action-btn action-like"
      :class="{ 'is-active': liked }"
      :aria-pressed="liked"
      @click="emit('toggle-like')"
    >
      <i :class="liked ? 'bi bi-heart-fill' : 'bi bi-heart'" aria-hidden="true"></i>
      <span>{{ likes }}</span>
    </button>

    <button
      v-if="isLoggedIn"
      type="button"
      class="action-btn action-save"
      :class="{ 'is-active': saved }"
      :aria-pressed="saved"
      @click="emit('toggle-save')"
    >
      <i :class="saved ? 'bi bi-bookmark-fill' : 'bi bi-bookmark'" aria-hidden="true"></i>
      <span>{{ saved ? 'Saved' : 'Save' }}</span>
    </button>

    <div v-if="isOwner" class="owner-actions">
      <button
        type="button"
        class="action-btn action-edit"
        @click="emit('edit')"
      >
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        <span>Edit</span>
      </button>
      <button
        type="button"
        class="action-btn action-delete"
        @click="emit('delete')"
      >
        <i class="bi bi-trash" aria-hidden="true"></i>
        <span>Delete</span>
      </button>
    </div>
  </div>
</template>